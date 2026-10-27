<?php

declare(strict_types=1);

namespace App\Domain\Customer\Services;

use App\Models\Customer;
use App\Support\Phone\EgyptianPhone;
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

    /**
     * Request an OTP for the given phone number.
     *
     * @throws ValidationException
     */
    public function requestOtp(string $phone): string
    {
        $parsed = EgyptianPhone::tryParse($phone);
        if ($parsed === null) {
            throw ValidationException::withMessages([
                'phone' => ['رقم الهاتف المصري غير صالح. يجب أن يبدأ بـ 010 أو 011 أو 012 أو 015 ويتكون من 11 رقماً.'],
            ]);
        }

        $phoneHash = $parsed->hash();
        $lockKey = "customer_otp_lock_{$phoneHash}";
        if (Cache::has($lockKey)) {
            $remaining = Cache::get($lockKey);

            throw ValidationException::withMessages([
                'phone' => ['تم قفل الحساب مؤقتاً لكثرة المحاولات الخاطئة. يرجى المحاولة بعد 15 دقيقة.'],
            ]);
        }

        // Rate limit: max 3 requests per 10 minutes
        $rateLimitKey = "customer_otp_rate_{$phoneHash}";
        $requests = (int) Cache::get($rateLimitKey, 0);
        if ($requests >= self::MAX_REQUESTS_PER_WINDOW) {
            throw ValidationException::withMessages([
                'phone' => ['تم تجاوز الحد الأقصى لطلب رمز التحقق (3 مرات كل 10 دقائق). يرجى الانتظار.'],
            ]);
        }

        Cache::put($rateLimitKey, $requests + 1, now()->addMinutes(self::REQUEST_WINDOW_MINUTES));

        // Generate 6-digit code
        $code = (string) random_int(100000, 999999);
        $otpKey = "customer_otp_code_{$phoneHash}";
        Cache::put($otpKey, $code, now()->addMinutes(self::OTP_TTL_MINUTES));

        // In production, send SMS. For now log it safely.
        Log::info("Customer OTP requested for {$parsed->e164}: {$code}");

        return $code;
    }

    /**
     * Verify the OTP and log in / register the customer.
     *
     * @throws ValidationException
     */
    public function verifyOtp(string $phone, string $code): Customer
    {
        $parsed = EgyptianPhone::tryParse($phone);
        if ($parsed === null) {
            throw ValidationException::withMessages([
                'phone' => ['رقم الهاتف المصري غير صالح.'],
            ]);
        }

        $phoneHash = $parsed->hash();
        $lockKey = "customer_otp_lock_{$phoneHash}";
        if (Cache::has($lockKey)) {
            throw ValidationException::withMessages([
                'code' => ['تم قفل الحساب مؤقتاً لكثرة المحاولات الخاطئة. يرجى المحاولة بعد 15 دقيقة.'],
            ]);
        }

        $otpKey = "customer_otp_code_{$phoneHash}";
        $cachedCode = Cache::get($otpKey);

        if ($cachedCode === null || trim($code) !== (string) $cachedCode) {
            $failedKey = "customer_otp_failed_{$phoneHash}";
            $failedCount = (int) Cache::get($failedKey, 0) + 1;
            Cache::put($failedKey, $failedCount, now()->addMinutes(self::LOCKOUT_MINUTES));

            if ($failedCount >= self::MAX_FAILED_ATTEMPTS) {
                Cache::put($lockKey, true, now()->addMinutes(self::LOCKOUT_MINUTES));
                Cache::forget($otpKey);

                throw ValidationException::withMessages([
                    'code' => ['تم قفل الحساب لمدة 15 دقيقة بسبب تكرار إدخال رمز التحقق بشكل خاطئ.'],
                ]);
            }

            throw ValidationException::withMessages([
                'code' => ['رمز التحقق غير صحيح أو انتهت صلاحيته.'],
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
