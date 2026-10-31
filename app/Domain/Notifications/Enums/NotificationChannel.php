<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Enums;

enum NotificationChannel: string
{
    case SMS = 'sms';
    case WHATSAPP = 'whatsapp';
    case EMAIL = 'email';
    case IN_APP = 'in_app';
    case WEB_PUSH = 'web_push';
    case MOBILE_PUSH = 'mobile_push';

    public function getLabel(): string
    {
        return match ($this) {
            self::SMS => __('رسالة نصية قصيرة (SMS)'),
            self::WHATSAPP => __('واتساب (WhatsApp)'),
            self::EMAIL => __('البريد الإلكتروني (Email)'),
            self::IN_APP => __('إشعار داخل التطبيق (In-App)'),
            self::WEB_PUSH => __('إشعار المتصفح (Web Push)'),
            self::MOBILE_PUSH => __('إشعار الجوال الفوري (Mobile Push)'),
        };
    }
}
