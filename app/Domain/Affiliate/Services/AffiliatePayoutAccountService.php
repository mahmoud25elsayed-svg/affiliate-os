<?php

declare(strict_types=1);

namespace App\Domain\Affiliate\Services;

use App\Domain\Affiliate\Settings\AffiliateProgramSettings;
use App\Domain\Notifications\Enums\NotificationChannel;
use App\Domain\Notifications\Services\NotificationManager;
use App\Models\Affiliate;
use App\Models\Tenant;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

class AffiliatePayoutAccountService
{
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
                    'store_name' => $tenant instanceof Tenant ? $tenant->name : 'متجرنا',
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
            throw new InvalidArgumentException('لم يتم طلب رمز تحقق لتحديث بيانات الدفع.');
        }

        if ($affiliate->otp_expires_at->isPast()) {
            throw new InvalidArgumentException('انتهت صلاحية رمز التحقق المدخل. يرجى طلب رمز جديد.');
        }

        if (trim($affiliate->otp_code) !== trim($otpCode)) {
            throw new InvalidArgumentException('رمز التحقق غير صحيح.');
        }

        // Validate method
        $validMethods = [
            Affiliate::PAYOUT_METHOD_WALLET,
            Affiliate::PAYOUT_METHOD_INSTAPAY,
            Affiliate::PAYOUT_METHOD_BANK,
        ];

        if (! in_array($method, $validMethods, true)) {
            throw new InvalidArgumentException("طريقة الدفع المختارة [{$method}] غير مدعومة.");
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
