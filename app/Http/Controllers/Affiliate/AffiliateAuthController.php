<?php

declare(strict_types=1);

namespace App\Http\Controllers\Affiliate;

use App\Domain\Affiliate\Exceptions\AffiliateLimitExceededException;
use App\Domain\Affiliate\Services\AffiliateRegistrationService;
use App\Domain\Affiliate\Settings\AffiliateProgramSettings;
use App\Http\Controllers\Controller;
use App\Models\Affiliate;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use InvalidArgumentException;

class AffiliateAuthController extends Controller
{
    public function __construct(
        protected AffiliateRegistrationService $registrationService
    ) {}

    public function showJoin(Request $request): View
    {
        $tenant = function_exists('tenancy') && tenancy()->initialized ? tenancy()->tenant : null;
        $settings = AffiliateProgramSettings::load();

        return view('affiliate.auth.join', [
            'storeName' => $tenant instanceof Tenant ? $tenant->name : __('متجرنا'),
            'referralCode' => $request->query('ref'),
            'settings' => $settings,
        ]);
    }

    public function register(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string'],
            'email' => ['nullable', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'referral_code' => ['nullable', 'string'],
        ], [
            'name.required' => __('الاسم مطلوب'),
            'phone.required' => __('رقم الهاتف مطلوب'),
            'password.required' => __('كلمة المرور مطلوبة'),
            'password.min' => __('يجب ألا تقل كلمة المرور عن 8 أحرف'),
            'password.confirmed' => __('تأكيد كلمة المرور غير متطابق'),
        ]);

        try {
            /** @var array{name: string, phone: string, email?: string|null, password: string, referral_code?: string|null} $validated */
            $affiliate = $this->registrationService->register($validated);
            session(['pending_affiliate_id' => $affiliate->id]);

            return redirect()->route('affiliate.verify-otp.show')
                ->with('success', __('تم تسجيل حسابك بنجاح! يرجى إدخال رمز التحقق (OTP) المرسل إلى هاتفك.'));
        } catch (AffiliateLimitExceededException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function showLogin(): View
    {
        $tenant = function_exists('tenancy') && tenancy()->initialized ? tenancy()->tenant : null;

        return view('affiliate.auth.login', [
            'storeName' => $tenant instanceof Tenant ? $tenant->name : __('متجرنا'),
        ]);
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'login' => ['required', 'string'], // phone or email
            'password' => ['required', 'string'],
        ], [
            'login.required' => __('يرجى إدخال رقم الهاتف أو البريد الإلكتروني'),
            'password.required' => __('كلمة المرور مطلوبة'),
        ]);

        $tenant = function_exists('tenancy') && tenancy()->initialized ? tenancy()->tenant : null;
        $tenantId = $tenant instanceof Tenant ? (string) $tenant->id : '';

        // Check if login is email or phone
        $loginInput = trim($credentials['login']);
        $query = Affiliate::query()->where('tenant_id', $tenantId);

        if (filter_var($loginInput, FILTER_VALIDATE_EMAIL)) {
            $query->where('email', $loginInput);
        } else {
            // Strip leading + or 00
            $cleaned = preg_replace('/^\+2|^2|^002/', '', $loginInput);
            $query->where(function ($q) use ($loginInput, $cleaned) {
                $q->where('phone', $loginInput)
                    ->orWhere('phone', '+20'.$cleaned)
                    ->orWhere('phone', '0'.$cleaned);
            });
        }

        /** @var Affiliate|null $affiliate */
        $affiliate = $query->first();

        if (! $affiliate || ! Auth::guard('affiliate')->attempt(['id' => $affiliate->id, 'password' => $credentials['password']], (bool) $request->boolean('remember'))) {
            return back()->withInput()->with('error', __('بيانات الدخول غير صحيحة.'));
        }

        // Check status
        if ($affiliate->isSuspended()) {
            Auth::guard('affiliate')->logout();

            return back()->with('error', __('حسابك معلق حالياً: ').($affiliate->status_reason ?: __('يرجى التواصل مع إدارة المتجر.')));
        }

        if ($affiliate->isRejected()) {
            Auth::guard('affiliate')->logout();

            return back()->with('error', __('تم رفض طلب انضمامك للبرنامج: ').($affiliate->status_reason ?: __('غير مؤهل.')));
        }

        return redirect()->intended(route('affiliate.dashboard'));
    }

    public function showVerifyOtp(): View
    {
        $affiliateId = session('pending_affiliate_id');
        /** @var Affiliate|null $affiliate */
        $affiliate = $affiliateId ? Affiliate::find($affiliateId) : null;

        return view('affiliate.auth.verify-otp', [
            'affiliate' => $affiliate,
        ]);
    }

    public function verifyOtp(Request $request): RedirectResponse
    {
        $request->validate([
            'code' => ['required', 'string', 'size:6'],
        ], [
            'code.required' => __('رمز التحقق مطلوب'),
            'code.size' => __('رمز التحقق يتكون من 6 أرقام'),
        ]);

        $affiliateId = session('pending_affiliate_id') ?: Auth::guard('affiliate')->id();
        /** @var Affiliate|null $affiliate */
        $affiliate = $affiliateId ? Affiliate::find($affiliateId) : null;

        if (! $affiliate) {
            return redirect()->route('affiliate.login')->with('error', __('انتهت الجلسة، يرجى تسجيل الدخول أولاً.'));
        }

        $code = (string) $request->input('code');
        if (! $this->registrationService->verifyPhoneOtp($affiliate, $code)) {
            return back()->with('error', __('رمز التحقق غير صحيح أو انتهت صلاحيته.'));
        }

        session()->forget('pending_affiliate_id');
        Auth::guard('affiliate')->login($affiliate);

        if ($affiliate->isPending()) {
            return redirect()->route('affiliate.dashboard')
                ->with('info', __('تم تأكيد رقم هاتفك بنجاح! حسابك في انتظار موافقة إدارة المتجر لتفعيل حسابك.'));
        }

        return redirect()->route('affiliate.dashboard')
            ->with('success', __('تم تفعيل حسابك بنجاح! أهلاً بك في لوحة تحكم المسوق.'));
    }

    public function logout(): RedirectResponse
    {
        Auth::guard('affiliate')->logout();

        return redirect()->route('affiliate.login')->with('success', __('تم تسجيل الخروج بنجاح.'));
    }
}
