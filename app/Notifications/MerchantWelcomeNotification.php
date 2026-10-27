<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MerchantWelcomeNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Tenant $tenant
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $storeUrl = url("/store/{$this->tenant->id}");

        return (new MailMessage)
            ->subject("مرحباً بك في AffiliateOS - متجرك جاهز الآن! ({$this->tenant->name})")
            ->greeting("أهلاً بك، {$this->tenant->name}")
            ->line('تم إنشاء وتجهيز متجرك الإلكتروني وبرنامج الأفلييت الخاص بك بنجاح.')
            ->line("معرف المتجر: {$this->tenant->id}")
            ->action('الدخول إلى لوحة تحكم التاجر', $storeUrl)
            ->line('نتمنى لك تجارة رابحة وتوسعاً مستمراً في مبيعاتك!')
            ->salutation('فريق عمل AffiliateOS');
    }
}
