<?php

declare(strict_types=1);

namespace App\Domain\Affiliate\Services;

use App\Domain\Affiliate\Exceptions\AffiliateLimitExceededException;
use App\Domain\Affiliate\Settings\AffiliateProgramSettings;
use App\Domain\Notifications\Enums\NotificationChannel;
use App\Domain\Notifications\Services\NotificationManager;
use App\Models\Affiliate;
use App\Models\Plan;
use App\Models\Tenant;
use App\Support\Phone\PhoneNumber;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use InvalidArgumentException;

class AffiliateRegistrationService
{
    public const MAX_OTP_ATTEMPTS = 5;

    public function __construct(
        protected NotificationManager $notificationManager
    ) {}

    /**
     * @param  array{
     *     name: string,
     *     phone: string,
     *     email?: string|null,
     *     password: string,
     *     referral_code?: string|null
     * }  $data
     *
     * @throws AffiliateLimitExceededException
     * @throws InvalidArgumentException
     */
    public function register(array $data): Affiliate
    {
        $tenant = function_exists('tenancy') && tenancy()->initialized ? tenancy()->tenant : null;
        $tenantId = $tenant instanceof Tenant ? (string) $tenant->id : '';

        // 1. Enforce Plan Limits
        if ($tenant instanceof Tenant) {
            $subscription = $tenant->subscription;
            $plan = $subscription?->plan;
            if ($plan instanceof Plan) {
                if (! $plan->hasFeature('affiliate')) {
                    throw AffiliateLimitExceededException::featureDisabled();
                }

                $maxAffiliates = $plan->getLimit('affiliates');
                if ($maxAffiliates > 0) {
                    $currentCount = Affiliate::query()->where('tenant_id', $tenantId)->count();
                    if ($currentCount >= $maxAffiliates) {
                        throw AffiliateLimitExceededException::forLimit($maxAffiliates);
                    }
                }
            }
        }

        // 2. Validate and normalise the mobile number (store country or international format)
        $parsedPhone = PhoneNumber::tryParse($data['phone']);
        if (! $parsedPhone) {
            throw new InvalidArgumentException(PhoneNumber::invalidMessage());
        }

        $phoneE164 = $parsedPhone->e164;

        // Check uniqueness within tenant
        if (Affiliate::query()->where('tenant_id', $tenantId)->where('phone', $phoneE164)->exists()) {
            throw new InvalidArgumentException(__('رقم الهاتف مسجل بالفعل في برنامج المسوقين لهذا المتجر.'));
        }

        if (! empty($data['email'])) {
            if (Affiliate::query()->where('tenant_id', $tenantId)->where('email', $data['email'])->exists()) {
                throw new InvalidArgumentException(__('البريد الإلكتروني مسجل بالفعل في برنامج المسوقين لهذا المتجر.'));
            }
        }

        // 3. Resolve Referrer if code provided
        $referredById = null;
        if (! empty($data['referral_code'])) {
            /** @var Affiliate|null $referrer */
            $referrer = Affiliate::query()
                ->where('tenant_id', $tenantId)
                ->where('code', trim($data['referral_code']))
                ->first();

            if ($referrer) {
                $referredById = $referrer->id;
            }
        }

        // 4. Generate Unique Affiliate Code
        $code = $this->generateUniqueCode($tenantId);

        // 5. Load Store Settings for Auto-approval & Commission
        $settings = AffiliateProgramSettings::load();
        $status = $settings->shouldAutoApprove() ? Affiliate::STATUS_ACTIVE : Affiliate::STATUS_PENDING;

        // 6. Generate OTP Code
        $otp = (string) random_int(100000, 999999);
        $otpExpiresAt = Carbon::now()->addMinutes(10);

        /** @var Affiliate $affiliate */
        $affiliate = Affiliate::create([
            'tenant_id' => $tenantId,
            'name' => trim($data['name']),
            'code' => $code,
            'phone' => $phoneE164,
            'email' => ! empty($data['email']) ? strtolower(trim($data['email'])) : null,
            'password' => Hash::make($data['password']),
            'status' => $status,
            'commission_rate' => $settings->default_commission_rate,
            'referred_by_id' => $referredById,
            'otp_code' => $otp,
            'otp_expires_at' => $otpExpiresAt,
            'kyc_status' => Affiliate::KYC_UNSUBMITTED,
        ]);

        // 7. Send OTP via SMS
        $this->notificationManager->send(
            channel: NotificationChannel::SMS,
            recipient: $phoneE164,
            eventType: 'otp_verification',
            data: [
                'customer_name' => $affiliate->name,
                'otp_code' => $otp,
                'store_name' => $tenant instanceof Tenant ? $tenant->name : __('متجرنا'),
            ],
            notifiable: $affiliate
        );

        return $affiliate;
    }

    public function verifyPhoneOtp(Affiliate $affiliate, string $code): bool
    {
        if ($affiliate->otp_code === null || $affiliate->otp_expires_at === null) {
            return false;
        }

        if ($affiliate->otp_expires_at->isPast()) {
            return false;
        }

        if (! hash_equals(trim($affiliate->otp_code), trim($code))) {
            // Burn the code after repeated wrong guesses so it cannot be brute-forced across IPs.
            $attemptsKey = "affiliate_phone_otp_failures:{$affiliate->getKey()}";
            $failures = (int) Cache::get($attemptsKey, 0) + 1;
            Cache::put($attemptsKey, $failures, $affiliate->otp_expires_at);
            if ($failures >= self::MAX_OTP_ATTEMPTS) {
                $affiliate->updateQuietly(['otp_code' => null, 'otp_expires_at' => null]);
                Cache::forget($attemptsKey);
            }

            return false;
        }

        Cache::forget("affiliate_phone_otp_failures:{$affiliate->getKey()}");
        $affiliate->verifyPhone();

        return true;
    }

    public function resendPhoneOtp(Affiliate $affiliate): void
    {
        $otp = (string) random_int(100000, 999999);
        $otpExpiresAt = Carbon::now()->addMinutes(10);

        $affiliate->updateQuietly([
            'otp_code' => $otp,
            'otp_expires_at' => $otpExpiresAt,
        ]);
        $affiliate->otp_code = $otp;
        $affiliate->otp_expires_at = $otpExpiresAt;

        if ($affiliate->phone) {
            $tenant = function_exists('tenancy') && tenancy()->initialized ? tenancy()->tenant : null;
            $this->notificationManager->send(
                channel: NotificationChannel::SMS,
                recipient: $affiliate->phone,
                eventType: 'otp_verification',
                data: [
                    'customer_name' => $affiliate->name,
                    'otp_code' => $otp,
                    'store_name' => $tenant instanceof Tenant ? $tenant->name : __('متجرنا'),
                ],
                notifiable: $affiliate
            );
        }
    }

    protected function generateUniqueCode(string $tenantId): string
    {
        do {
            $code = 'AFF-'.strtoupper(Str::random(6));
        } while (Affiliate::query()->where('tenant_id', $tenantId)->where('code', $code)->exists());

        return $code;
    }
}
