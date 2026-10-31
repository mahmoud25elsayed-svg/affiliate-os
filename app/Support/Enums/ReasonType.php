<?php

declare(strict_types=1);

namespace App\Support\Enums;

enum ReasonType: string
{
    case CANCELLATION = 'cancellation';
    case RETURN = 'return';
    case QUALITY_REJECTION = 'quality_rejection';
    case DELIVERY_FAILURE = 'delivery_failure';

    public function label(string $locale = 'ar'): string
    {
        return match ($this) {
            self::CANCELLATION => $locale === 'ar' ? __('إلغاء الطلب') : 'Cancellation',
            self::RETURN => $locale === 'ar' ? __('مرتجع') : 'Return',
            self::QUALITY_REJECTION => $locale === 'ar' ? __('رفض جودة الطلب') : 'Quality Rejection',
            self::DELIVERY_FAILURE => $locale === 'ar' ? __('فشل التسليم') : 'Delivery Failure',
        };
    }
}
