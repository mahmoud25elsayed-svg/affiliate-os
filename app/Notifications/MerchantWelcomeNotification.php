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
            ->subject(__('مرحباً بك في AffiliateOS - متجرك جاهز الآن! (:name)', ['name' => $this->tenant->name]))
            ->greeting(__('أهلاً بك، :name', ['name' => $this->tenant->name]))
            ->line(__('تم إنشاء وتجهيز متجرك الإلكتروني وبرنامج الأفلييت الخاص بك بنجاح.'))
            ->line(__('معرف المتجر: :id', ['id' => $this->tenant->id]))
            ->action(__('الدخول إلى لوحة تحكم التاجر'), $storeUrl)
            ->line(__('نتمنى لك تجارة رابحة وتوسعاً مستمراً في مبيعاتك!'))
            ->salutation(__('فريق عمل AffiliateOS'));
    }
}
