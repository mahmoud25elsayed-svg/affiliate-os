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
            self::ORDER_CONFIRMED => __('تأكيد الطلب'),
            self::ORDER_SHIPPED => __('شحن الطلب'),
            self::ORDER_DELIVERED => __('تسليم الطلب'),
            self::ORDER_CANCELLED => __('إلغاء الطلب'),
            self::CART_ABANDONED => __('سلة متروكة'),
            self::OTP_VERIFICATION => __('رمز التحقق (OTP)'),
            self::WELCOME => __('رسالة الترحيب / الاشتراك'),
        };
    }
}
