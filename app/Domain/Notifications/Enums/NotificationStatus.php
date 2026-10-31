<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Enums;

enum NotificationStatus: string
{
    case PENDING = 'pending';
    case SENT = 'sent';
    case DELIVERED = 'delivered';
    case FAILED = 'failed';

    public function getLabel(): string
    {
        return match ($this) {
            self::PENDING => __('قيد الإرسال'),
            self::SENT => __('تم الإرسال'),
            self::DELIVERED => __('تم التسليم'),
            self::FAILED => __('فشل الإرسال'),
        };
    }
}
