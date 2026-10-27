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
            self::PENDING => 'قيد الإرسال',
            self::SENT => 'تم الإرسال',
            self::DELIVERED => 'تم التسليم',
            self::FAILED => 'فشل الإرسال',
        };
    }
}
