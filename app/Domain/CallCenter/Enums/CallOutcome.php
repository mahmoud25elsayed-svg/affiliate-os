<?php

declare(strict_types=1);

namespace App\Domain\CallCenter\Enums;

enum CallOutcome: string
{
    case CONFIRMED = 'confirmed';
    case NO_ANSWER = 'no_answer';
    case BUSY = 'busy';
    case UNREACHABLE = 'unreachable';
    case CUSTOMER_CANCELLED = 'customer_cancelled';
    case POSTPONED = 'postponed';
    case WRONG_NUMBER = 'wrong_number';

    public function label(string $locale = 'ar'): string
    {
        return match ($this) {
            self::CONFIRMED => $locale === 'ar' ? __('تم التأكيد') : 'Confirmed',
            self::NO_ANSWER => $locale === 'ar' ? __('لم يرد') : 'No Answer',
            self::BUSY => $locale === 'ar' ? __('مشغول / تعذر الاتصال') : 'Busy',
            self::UNREACHABLE => $locale === 'ar' ? __('غير متاح / مغلق') : 'Unreachable',
            self::CUSTOMER_CANCELLED => $locale === 'ar' ? __('العميل ألغى') : 'Cancelled by Customer',
            self::POSTPONED => $locale === 'ar' ? __('طلب تأجيل / معاودة الاتصال') : 'Postponed',
            self::WRONG_NUMBER => $locale === 'ar' ? __('رقم خاطئ') : 'Wrong Number',
        };
    }

    public function isSuccess(): bool
    {
        return $this === self::CONFIRMED;
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::CONFIRMED, self::CUSTOMER_CANCELLED, self::WRONG_NUMBER], true);
    }

    public function isRetryable(): bool
    {
        return in_array($this, [self::NO_ANSWER, self::BUSY, self::UNREACHABLE, self::POSTPONED], true);
    }
}
