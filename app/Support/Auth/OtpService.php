<?php

declare(strict_types=1);

namespace App\Support\Auth;

use App\Support\Phone\EgyptianPhone;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class OtpService
{
    public const EXPIRATION_SECONDS = 300; // 5 minutes

    public const MAX_ATTEMPTS_PER_MINUTE = 3;

    /**
     * Send OTP to Egyptian phone number.
     *
     * @return array{success: bool, message?: string, code?: string|null}
     */
    public function sendOtp(string $phone): array
    {
        $parsed = EgyptianPhone::tryParse($phone);
        if (! $parsed) {
            return [
                'success' => false,
                'message' => 'رقم الهاتف المصري غير صحيح.',
            ];
        }

        $normalized = $parsed->e164;

        $rateKey = 'otp_rate:'.md5($normalized);
        $attempts = (int) Cache::get($rateKey, 0);
        if ($attempts >= self::MAX_ATTEMPTS_PER_MINUTE) {
            return [
                'success' => false,
                'message' => 'تجاوزت الحد المسموح لإرسال الرموز. الرجاء الانتظار دقيقة واحدة.',
            ];
        }
        Cache::put($rateKey, $attempts + 1, 60);

        // In test/local environment, if phone is test-phone or app in testing, allow deterministic code
        $code = app()->environment('testing')
            ? '123456'
            : (string) random_int(100000, 999999);

        $cacheKey = 'otp_code:'.md5($normalized);
        Cache::put($cacheKey, $code, self::EXPIRATION_SECONDS);

        Log::info('OTP generated for Egyptian phone', [
            'phone_hash' => $parsed->hash(config('app.key', 'secret')),
            'environment' => app()->environment(),
        ]);

        return [
            'success' => true,
            'code' => app()->environment(['local', 'testing']) ? $code : null,
        ];
    }

    /**
     * Verify OTP for Egyptian phone number.
     */
    public function verifyOtp(string $phone, string $code): bool
    {
        $parsed = EgyptianPhone::tryParse($phone);
        if (! $parsed) {
            return false;
        }

        $normalized = $parsed->e164;

        // Test fallback
        if (app()->environment('testing') && $code === '123456') {
            return true;
        }

        $cacheKey = 'otp_code:'.md5($normalized);
        $cachedCode = Cache::get($cacheKey);

        if ($cachedCode && hash_equals((string) $cachedCode, trim($code))) {
            Cache::forget($cacheKey);

            return true;
        }

        return false;
    }
}
