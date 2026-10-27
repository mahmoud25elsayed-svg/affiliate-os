<?php

declare(strict_types=1);

namespace App\Support\Auth;

use Illuminate\Support\Facades\Cache;

class FailedAttemptsGuard
{
    public const MAX_ATTEMPTS_BEFORE_CAPTCHA = 5;

    public const LOCKOUT_SECONDS = 900; // 15 minutes

    /**
     * Check if captcha is required for given identifier (IP or email).
     */
    public static function isCaptchaRequired(string $key): bool
    {
        $attempts = (int) Cache::get(self::cacheKey($key), 0);

        return $attempts >= self::MAX_ATTEMPTS_BEFORE_CAPTCHA;
    }

    /**
     * Record a failed login attempt.
     */
    public static function recordFailure(string $key): int
    {
        $cacheKey = self::cacheKey($key);
        $attempts = (int) Cache::get($cacheKey, 0) + 1;

        Cache::put($cacheKey, $attempts, self::LOCKOUT_SECONDS);

        return $attempts;
    }

    /**
     * Reset failed attempts after successful login.
     */
    public static function reset(string $key): void
    {
        Cache::forget(self::cacheKey($key));
    }

    /**
     * Get remaining attempts before captcha.
     */
    public static function remainingAttempts(string $key): int
    {
        $attempts = (int) Cache::get(self::cacheKey($key), 0);

        return max(0, self::MAX_ATTEMPTS_BEFORE_CAPTCHA - $attempts);
    }

    private static function cacheKey(string $key): string
    {
        return 'auth_failed_attempts:'.md5($key);
    }
}
