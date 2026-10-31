<?php

declare(strict_types=1);

namespace App\Support\Auth;

use App\Domain\Notifications\Drivers\SMS\SmsMisrDriver;
use App\Domain\Notifications\DTOs\NotificationMessage;
use App\Domain\Notifications\Enums\NotificationChannel;
use App\Support\Phone\PhoneNumber;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class OtpService
{
    public const EXPIRATION_SECONDS = 300; // 5 minutes

    public const MAX_ATTEMPTS_PER_MINUTE = 3;

    public const MAX_ATTEMPTS_PER_DAY = 10;

    public const MAX_ATTEMPTS_PER_IP_HOUR = 15;

    public const MAX_VERIFY_ATTEMPTS = 5;

    /**
     * Send OTP to a mobile number with IP & Daily throttling and SMS delivery.
     *
     * @return array{success: bool, message?: string, code?: string|null}
     */
    public function sendOtp(string $phone, ?string $clientIp = null, ?string $country = null): array
    {
        $parsed = PhoneNumber::tryParse($phone, $country);
        if (! $parsed) {
            return [
                'success' => false,
                'message' => PhoneNumber::invalidMessage($country),
            ];
        }

        $normalized = $parsed->e164;

        // 1. IP rate limiting (SEC-07: anti-bombing)
        if ($clientIp) {
            $ipKey = 'otp_ip_rate:'.md5($clientIp);
            $ipAttempts = (int) Cache::get($ipKey, 0);
            if ($ipAttempts >= self::MAX_ATTEMPTS_PER_IP_HOUR) {
                return [
                    'success' => false,
                    'message' => __('تم تجاوز الحد الأقصى للطلبات من هذا العنوان. الرجاء المحاولة لاحقاً.'),
                ];
            }
            Cache::put($ipKey, $ipAttempts + 1, 3600);
        }

        // 2. Daily per-phone rate limiting
        $dailyKey = 'otp_daily_rate:'.md5($normalized);
        $dailyAttempts = (int) Cache::get($dailyKey, 0);
        if ($dailyAttempts >= self::MAX_ATTEMPTS_PER_DAY) {
            return [
                'success' => false,
                'message' => __('تم تجاوز الحد الأقصى اليومي لرموز التحقق لهذا الرقم.'),
            ];
        }
        Cache::put($dailyKey, $dailyAttempts + 1, 86400);

        // 3. Per-minute rate limiting
        $rateKey = 'otp_rate:'.md5($normalized);
        $attempts = (int) Cache::get($rateKey, 0);
        if ($attempts >= self::MAX_ATTEMPTS_PER_MINUTE) {
            return [
                'success' => false,
                'message' => __('تجاوزت الحد المسموح لإرسال الرموز. الرجاء الانتظار دقيقة واحدة.'),
            ];
        }
        Cache::put($rateKey, $attempts + 1, 60);

        // 4. Generate code
        $code = app()->environment('testing')
            ? '123456'
            : (string) random_int(100000, 999999);

        $cacheKey = 'otp_code:'.md5($normalized);
        Cache::put($cacheKey, $code, self::EXPIRATION_SECONDS);

        // 5. Send SMS via configured SMS driver (SEC-05).
        // Without credentials the driver returns a failure result without any HTTP call.
        try {
            $result = app(SmsMisrDriver::class)->send(new NotificationMessage(
                channel: NotificationChannel::SMS,
                recipient: $normalized,
                content: __('رمز التحقق الخاص بك في AffiliateOS هو: :code. صالح لمدة 5 دقائق.', ['code' => $code]),
                eventType: 'otp',
                senderId: 'AffiliateOS'
            ));
            if (! $result->success) {
                Log::warning('SMS delivery attempted but failed or not configured', [
                    'phone_hash' => $parsed->hash((string) config('app.key', 'secret')),
                    'error' => $result->errorMessage,
                ]);
            }
            $delivered = $result->success;
        } catch (\Throwable $e) {
            Log::error('SMS delivery exception: '.$e->getMessage());
            $delivered = false;
        }

        // Never tell a real user "code sent" when nothing left the server. Local/testing keep the
        // on-screen code so development works without an SMS account.
        if (! $delivered && ! app()->environment(['local', 'testing'])) {
            Cache::forget($cacheKey);

            return [
                'success' => false,
                'message' => __('تعذر إرسال رمز التحقق إلى هاتفك حالياً. حاول مرة أخرى بعد قليل أو تواصل مع الدعم.'),
            ];
        }

        Log::info('OTP generated for phone', [
            'phone_hash' => $parsed->hash(config('app.key', 'secret')),
            'environment' => app()->environment(),
        ]);

        return [
            'success' => true,
            'code' => app()->environment(['local', 'testing']) ? $code : null,
        ];
    }

    /**
     * Verify OTP for a mobile number.
     */
    public function verifyOtp(string $phone, string $code): bool
    {
        $parsed = PhoneNumber::tryParse($phone);
        if (! $parsed) {
            return false;
        }

        $normalized = $parsed->e164;
        $cacheKey = 'otp_code:'.md5($normalized);
        $attemptsKey = 'otp_verify_attempts:'.md5($normalized);

        $failedAttempts = (int) Cache::get($attemptsKey, 0);
        if ($failedAttempts >= self::MAX_VERIFY_ATTEMPTS) {
            Cache::forget($cacheKey);

            return false;
        }

        // Test fallback (only when no real code or test env)
        if (app()->environment('testing') && $code === '123456') {
            return true;
        }

        $cachedCode = Cache::get($cacheKey);

        if ($cachedCode && hash_equals((string) $cachedCode, trim($code))) {
            Cache::forget($cacheKey);
            Cache::forget($attemptsKey);

            return true;
        }

        Cache::put($attemptsKey, $failedAttempts + 1, self::EXPIRATION_SECONDS);
        if ($failedAttempts + 1 >= self::MAX_VERIFY_ATTEMPTS) {
            Cache::forget($cacheKey); // Lock and invalidate after 5 failed guesses
        }

        return false;
    }
}
