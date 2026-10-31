<?php

declare(strict_types=1);

namespace App\Domain\Affiliate\Services;

use App\Domain\Affiliate\Settings\AffiliateProgramSettings;
use App\Domain\Notifications\Enums\NotificationChannel;
use App\Domain\Notifications\Services\NotificationManager;
use App\Models\Affiliate;
use App\Models\Tenant;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;

class AffiliatePayoutAccountService
{
    public const MAX_OTP_ATTEMPTS = 5;

    public function __construct(
        protected NotificationManager $notificationManager
    ) {}

    public function requestChangeOtp(Affiliate $affiliate): void
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

    /**
     * @param  array<string, mixed>  $details
     *
     * @throws InvalidArgumentException
     */
    public function updatePayoutDetails(
        Affiliate $affiliate,
        string $method,
        array $details,
        string $otpCode
    ): void {
        // Validate OTP
        if ($affiliate->otp_code === null || $affiliate->otp_expires_at === null) {
            throw new InvalidArgumentException(__('لم يتم طلب رمز تحقق لتحديث بيانات الدفع.'));
        }

        if ($affiliate->otp_expires_at->isPast()) {
            throw new InvalidArgumentException(__('انتهت صلاحية رمز التحقق المدخل. يرجى طلب رمز جديد.'));
        }

        if (! hash_equals(trim($affiliate->otp_code), trim($otpCode))) {
            // Burn the code after repeated wrong guesses so payout details cannot be hijacked by brute force.
            $attemptsKey = "affiliate_payout_otp_failures:{$affiliate->getKey()}";
            $failures = (int) Cache::get($attemptsKey, 0) + 1;
            Cache::put($attemptsKey, $failures, $affiliate->otp_expires_at);
            if ($failures >= self::MAX_OTP_ATTEMPTS) {
                $affiliate->updateQuietly(['otp_code' => null, 'otp_expires_at' => null]);
                Cache::forget($attemptsKey);

                throw new InvalidArgumentException(__('تم إلغاء رمز التحقق بسبب تكرار الإدخال الخاطئ. يرجى طلب رمز جديد.'));
            }

            throw new InvalidArgumentException(__('رمز التحقق غير صحيح.'));
        }

        Cache::forget("affiliate_payout_otp_failures:{$affiliate->getKey()}");

        // Validate method
        $validMethods = [
            Affiliate::PAYOUT_METHOD_WALLET,
            Affiliate::PAYOUT_METHOD_INSTAPAY,
            Affiliate::PAYOUT_METHOD_BANK,
        ];

        if (! in_array($method, $validMethods, true)) {
            throw new InvalidArgumentException(__('طريقة الدفع المختارة [:method] غير مدعومة.', ['method' => $method]));
        }

        $settings = AffiliateProgramSettings::load();
        $freezeHours = $settings->getPayoutFreezeHours();

        // Update details (encrypted by cast) and freeze payouts for 24h
        $affiliate->update([
            'payout_method' => $method,
            'payout_details' => $details,
            'otp_code' => null,
            'otp_expires_at' => null,
            'payouts_frozen_until' => Carbon::now()->addHours($freezeHours),
        ]);
    }
}
