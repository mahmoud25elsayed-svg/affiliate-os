<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Listeners;

use App\Domain\Notifications\Enums\NotificationChannel;
use App\Domain\Notifications\Services\NotificationManager;
use App\Domain\Promotions\Events\AbandonedCartDetected;
use App\Models\Tenant;
use App\Support\Money;
use Throwable;

class DispatchAbandonedCartNotification
{
    public function __construct(
        protected NotificationManager $notificationManager
    ) {}

    public function handle(AbandonedCartDetected $event): void
    {
        $cart = $event->abandonedCart;
        $recipientPhone = $cart->phone_e164 ?: $cart->customer?->phone;
        $recipientEmail = $cart->email ?: $cart->customer?->email;
        $customerName = $cart->customer_name ?: __('عميلنا العزيز');
        $tenant = function_exists('tenancy') && tenancy()->initialized ? tenancy()->tenant : null;
        $storeName = $tenant instanceof Tenant ? $tenant->name : __('متجرنا');
        $recoveryUrl = url("/cart/recover/{$cart->recovery_token}");
        $totalFormatted = Money::fromMinor($cart->subtotal_minor)->format();

        $data = [
            'customer_name' => $customerName,
            'store_name' => $storeName,
            'recovery_url' => $recoveryUrl,
            'cart_total' => $totalFormatted,
            'items_count' => (string) ($cart->items_count ?: count((array) ($cart->cart_data['items'] ?? []))),
        ];

        if (! empty($recipientPhone)) {
            try {
                // Send WhatsApp or SMS
                $this->notificationManager->send(
                    channel: NotificationChannel::WHATSAPP,
                    recipient: $recipientPhone,
                    eventType: 'cart_abandoned',
                    data: $data,
                    notifiable: $cart
                );
            } catch (Throwable) {
                // Non-blocking
            }
        } elseif (! empty($recipientEmail)) {
            try {
                $this->notificationManager->send(
                    channel: NotificationChannel::EMAIL,
                    recipient: $recipientEmail,
                    eventType: 'cart_abandoned',
                    data: $data,
                    notifiable: $cart
                );
            } catch (Throwable) {
                // Non-blocking
            }
        }
    }
}
