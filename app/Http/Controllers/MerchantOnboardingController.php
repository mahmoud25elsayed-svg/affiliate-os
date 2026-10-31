<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Jobs\ProvisionTenant;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\MerchantWelcomeNotification;
use App\Support\Auth\OtpService;
use App\Support\Location\Country;
use App\Support\Location\Market;
use App\Support\Phone\PhoneNumber;
use App\Support\Tenancy\ReservedSubdomains;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class MerchantOnboardingController extends Controller
{
    public function __construct(
        protected OtpService $otpService
    ) {}

    public function showLanding(): View
    {
        return view('landing');
    }

    public function showRegister(): View
    {
        return view('onboarding.register');
    }

    /**
     * Check if a subdomain is available.
     */
    public function checkSubdomain(Request $request): JsonResponse
    {
        $ip = $request->ip() ?? '127.0.0.1';
        if (RateLimiter::tooManyAttempts('check_subdomain:'.$ip, 30)) {
            return response()->json([
                'available' => false,
                'message' => 'تجاوزت الحد المسموح للاستعلام عن النطاقات. يرجى الانتظار دقيقة واحدة.',
            ], 429);
        }
        RateLimiter::hit('check_subdomain:'.$ip, 60);

        $subdomain = Str::lower(trim((string) $request->input('subdomain')));

        // SEC-09: RFC 1123 compliant hostname check (no leading/trailing hyphens, no underscores)
        if (
            strlen($subdomain) < 3 ||
            strlen($subdomain) > 30 ||
            ! preg_match('/^[a-z0-9]([a-z0-9-]*[a-z0-9])?$/', $subdomain) ||
            str_contains($subdomain, '_') ||
            str_starts_with($subdomain, '-') ||
            str_ends_with($subdomain, '-')
        ) {
            return response()->json([
                'available' => false,
                'message' => 'يجب أن يتكون اسم النطاق من 3 إلى 30 حرفاً أو رقماً إنجليزياً، وألا يبدأ أو ينتهي بشرطة ولا يحتوي على رموز خاصة أو شرطات سفلية.',
            ]);
        }

        if (ReservedSubdomains::isReserved($subdomain)) {
            return response()->json([
                'available' => false,
                'message' => 'هذا الاسم محجوز لنظام المنصة، يرجى اختيار اسم آخر.',
            ]);
        }

        if (Tenant::where('id', $subdomain)->exists()) {
            return response()->json([
                'available' => false,
                'message' => 'هذا النطاق مستخدم بالفعل، يرجى اختيار اسم آخر.',
            ]);
        }

        return response()->json([
            'available' => true,
            'message' => 'النطاق متاح للتسجيل!',
        ]);
    }

    /**
     * Send OTP to phone number.
     */
    public function sendOtp(Request $request): JsonResponse
    {
        $phone = (string) $request->input('phone');
        $country = Country::find((string) $request->input('country'))?->code;

        $result = $this->otpService->sendOtp($phone, (string) $request->ip(), $country);

        return response()->json($result, $result['success'] ? 200 : 422);
    }

    /**
     * Handle Merchant registration.
     */
    public function register(Request $request): JsonResponse|RedirectResponse
    {
        // Rate limiting: 5 registrations per hour per IP
        $ip = $request->ip() ?? '127.0.0.1';
        $rateKey = 'merchant_reg:'.$ip;

        if (RateLimiter::tooManyAttempts($rateKey, 5)) {
            $seconds = RateLimiter::availableIn($rateKey);

            throw ValidationException::withMessages([
                'email' => ["تم تجاوز الحد المسموح للتسجيل. يرجى المحاولة بعد {$seconds} ثانية."],
            ]);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'store_name' => ['required', 'string', 'max:255'],
            'subdomain' => [
                'required',
                'string',
                'min:3',
                'max:30',
                'regex:/^[a-z0-9]([a-z0-9-]*[a-z0-9])?$/',
                'not_regex:/[_]/',
            ],
            'country' => ['nullable', 'string', Rule::in(array_keys((array) config('countries')))],
            'phone' => ['required', 'string'],
            'otp' => ['required', 'string'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
        ]);

        $subdomain = Str::lower(trim($validated['subdomain']));

        // Check reserved words
        if (ReservedSubdomains::isReserved($subdomain)) {
            throw ValidationException::withMessages([
                'subdomain' => ['هذا النطاق محجوز لنظام المنصة وغير متاح للاستخدام.'],
            ]);
        }

        // Check tenant uniqueness
        if (Tenant::where('id', $subdomain)->exists()) {
            throw ValidationException::withMessages([
                'subdomain' => ['هذا النطاق مستخدم بالفعل من قبل تاجر آخر.'],
            ]);
        }

        // The store's home country decides its currency, phone format and regions.
        $country = Country::find($validated['country'] ?? null) ?? Country::get(Market::DEFAULT_COUNTRY);

        $parsedPhone = PhoneNumber::tryParse($validated['phone'], $country->code);
        if (! $parsedPhone) {
            throw ValidationException::withMessages([
                'phone' => [PhoneNumber::invalidMessage($country->code)],
            ]);
        }
        $normalizedPhone = $parsedPhone->e164;

        // Verify OTP
        if (! $this->otpService->verifyOtp($normalizedPhone, $validated['otp'])) {
            throw ValidationException::withMessages([
                'otp' => ['رمز التحقق غير صحيح أو منتهي الصلاحية.'],
            ]);
        }

        RateLimiter::hit($rateKey, 3600);

        // 1+2. Create the tenant (provisioning state) and its owner atomically
        [$tenant, $owner] = DB::transaction(function () use ($subdomain, $validated, $normalizedPhone): array {
            /** @var Tenant $tenant */
            $tenant = Tenant::create([
                'id' => $subdomain,
                'name' => $validated['store_name'],
                'status' => 'provisioning',
            ]);

            /** @var User $owner */
            $owner = User::create([
                'tenant_id' => $tenant->id,
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'phone' => $normalizedPhone,
                'phone_verified_at' => now(),
            ]);

            return [$tenant, $owner];
        });

        // 3. Dispatch ProvisionTenant job (the owner already exists, so no credentials go into the queue payload)
        $rootDomain = (string) config('tenancy.root_domain', 'localhost');
        $tenantDomain = "{$tenant->id}.{$rootDomain}";

        ProvisionTenant::dispatch(
            tenantId: (string) $tenant->id,
            name: (string) $tenant->name,
            domain: $tenantDomain,
            ownerData: [
                'user_id' => (int) $owner->id,
                'name' => (string) $owner->name,
                'email' => (string) $owner->email,
                'phone' => $normalizedPhone,
            ],
            country: $country->code,
        );

        // 4. Send Welcome Notification
        $owner->notify(new MerchantWelcomeNotification($tenant));

        // 5. Authenticate merchant owner
        Auth::login($owner);

        $statusUrl = route('onboarding.provisioning', ['tenant' => $tenant->id]);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'redirect' => $statusUrl,
                'tenant_id' => $tenant->id,
            ]);
        }

        return redirect($statusUrl);
    }

    /**
     * Show provisioning status page / API.
     */
    public function provisioningStatus(Request $request, string $tenantId): View|JsonResponse|RedirectResponse
    {
        /** @var Tenant|null $tenant */
        $tenant = Tenant::find($tenantId);

        if (! $tenant) {
            abort(404, 'المتجر غير موجود.');
        }

        $storeUrl = url("/store/{$tenant->id}");

        if ($request->wantsJson()) {
            return response()->json([
                'status' => $tenant->status,
                'ready' => $tenant->isActive(),
                'store_url' => $storeUrl,
            ]);
        }

        if ($tenant->isActive()) {
            return redirect($storeUrl);
        }

        return view('onboarding.provisioning', [
            'tenant' => $tenant,
            'storeUrl' => $storeUrl,
        ]);
    }
}
