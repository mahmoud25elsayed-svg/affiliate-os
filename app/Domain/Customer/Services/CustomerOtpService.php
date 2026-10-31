<?php

declare(strict_types=1);

namespace App\Domain\Customer\Services;

use App\Domain\Notifications\Enums\NotificationChannel;
use App\Domain\Notifications\Services\NotificationManager;
use App\Models\Customer;
use App\Models\Tenant;
use App\Support\Phone\PhoneNumber;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class CustomerOtpService
{
    public const OTP_TTL_MINUTES = 5;

    public const MAX_REQUESTS_PER_WINDOW = 3;

    public const REQUEST_WINDOW_MINUTES = 10;

    public const MAX_FAILED_ATTEMPTS = 5;

    public const LOCKOUT_MINUTES = 15;

    public function __construct(
        protected NotificationManager $notificationManager
    ) {}

    /**
     * Request an OTP for the given phone number.
     *
     * @throws ValidationException
     */
    public function requestOtp(string $phone): string
    {
        $parsed = PhoneNumber::tryParse($phone);
        if ($parsed === null) {
            throw ValidationException::withMessages([
                'phone' => [PhoneNumber::invalidMessage()],
            ]);
        }

        $phoneHash = $parsed->hash();
        $lockKey = "customer_otp_lock_{$phoneHash}";
        if (Cache::has($lockKey)) {
            $remaining = Cache::get($lockKey);

            throw ValidationException::withMessages([
                'phone' => [__('تم قفل الحساب مؤقتاً لكثرة المحاولات الخاطئة. يرجى المحاولة بعد 15 دقيقة.')],
            ]);
        }

        // Rate limit: max 3 requests per 10 minutes
        $rateLimitKey = "customer_otp_rate_{$phoneHash}";
        $requests = (int) Cache::get($rateLimitKey, 0);
        if ($requests >= self::MAX_REQUESTS_PER_WINDOW) {
            throw ValidationException::withMessages([
                'phone' => [__('تم تجاوز الحد الأقصى لطلب رمز التحقق (3 مرات كل 10 دقائق). يرجى الانتظار.')],
            ]);
        }

        Cache::put($rateLimitKey, $requests + 1, now()->addMinutes(self::REQUEST_WINDOW_MINUTES));

        // Generate 6-digit code
        $code = (string) random_int(100000, 999999);
        $otpKey = "customer_otp_code_{$phoneHash}";
        Cache::put($otpKey, $code, now()->addMinutes(self::OTP_TTL_MINUTES));

        // Deliver by SMS. Never log the code or the raw phone number.
        try {
            $tenant = function_exists('tenancy') && tenancy()->initialized ? tenancy()->tenant : null;
            $this->notificationManager->send(
                channel: NotificationChannel::SMS,
                recipient: $parsed->e164,
                eventType: 'otp_verification',
                data: [
                    'otp_code' => $code,
                    'store_name' => $tenant instanceof Tenant ? $tenant->name : __('متجرنا'),
                ],
            );
        } catch (\Throwable $e) {
            Log::error('Customer OTP SMS dispatch failed: '.$e->getMessage(), ['phone_hash' => $phoneHash]);
        }

        return $code;
    }

    /**
     * Verify the OTP and log in / register the customer.
     *
     * @throws ValidationException
     */
    public function verifyOtp(string $phone, string $code): Customer
    {
        $parsed = PhoneNumber::tryParse($phone);
        if ($parsed === null) {
            throw ValidationException::withMessages([
                'phone' => [PhoneNumber::invalidMessage()],
            ]);
        }

        $phoneHash = $parsed->hash();
        $lockKey = "customer_otp_lock_{$phoneHash}";
        if (Cache::has($lockKey)) {
            throw ValidationException::withMessages([
                'code' => [__('تم قفل الحساب مؤقتاً لكثرة المحاولات الخاطئة. يرجى المحاولة بعد 15 دقيقة.')],
            ]);
        }

        $otpKey = "customer_otp_code_{$phoneHash}";
        $cachedCode = Cache::get($otpKey);

        if ($cachedCode === null || ! hash_equals((string) $cachedCode, trim($code))) {
            $failedKey = "customer_otp_failed_{$phoneHash}";
            $failedCount = (int) Cache::get($failedKey, 0) + 1;
            Cache::put($failedKey, $failedCount, now()->addMinutes(self::LOCKOUT_MINUTES));

            if ($failedCount >= self::MAX_FAILED_ATTEMPTS) {
                Cache::put($lockKey, true, now()->addMinutes(self::LOCKOUT_MINUTES));
                Cache::forget($otpKey);

                throw ValidationException::withMessages([
                    'code' => [__('تم قفل الحساب لمدة 15 دقيقة بسبب تكرار إدخال رمز التحقق بشكل خاطئ.')],
                ]);
            }

            throw ValidationException::withMessages([
                'code' => [__('رمز التحقق غير صحيح أو انتهت صلاحيته.')],
            ]);
        }

        // Successfully verified
        Cache::forget($otpKey);
        Cache::forget("customer_otp_failed_{$phoneHash}");

        $tenantId = function_exists('tenancy') && tenancy()->initialized ? (string) tenant('id') : 'default';

        /** @var Customer $customer */
        $customer = Customer::query()->firstOrCreate(
            [
                'tenant_id' => $tenantId,
                'phone_hash' => $phoneHash,
            ],
            [
                'first_name' => 'عميل',
                'phone_e164' => $parsed->e164,
                'phone_verified_at' => now(),
                'is_active' => true,
            ]
        );

        if (! $customer->phone_verified_at) {
            $customer->update(['phone_verified_at' => now()]);
        }

        return $customer;
    }
}
