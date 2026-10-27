<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Enums;

enum NotificationEventType: string
{
    case ORDER_CONFIRMED = 'order_confirmed';
    case ORDER_SHIPPED = 'order_shipped';
    case ORDER_DELIVERED = 'order_delivered';
    case ORDER_CANCELLED = 'order_cancelled';
    case CART_ABANDONED = 'cart_abandoned';
    case OTP_VERIFICATION = 'otp_verification';
    case WELCOME = 'welcome';

    public function getLabel(): string
    {
        return match ($this) {
            self::ORDER_CONFIRMED => 'تأكيد الطلب',
            self::ORDER_SHIPPED => 'شحن الطلب',
            self::ORDER_DELIVERED => 'تسليم الطلب',
            self::ORDER_CANCELLED => 'إلغاء الطلب',
            self::CART_ABANDONED => 'سلة متروكة',
            self::OTP_VERIFICATION => 'رمز التحقق (OTP)',
            self::WELCOME => 'رسالة الترحيب / الاشتراك',
        };
    }
}
