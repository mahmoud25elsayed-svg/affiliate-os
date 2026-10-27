<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Listeners;

use App\Domain\Notifications\Enums\NotificationChannel;
use App\Domain\Notifications\Services\NotificationManager;
use App\Domain\Orders\Events\OrderCancelled;
use App\Domain\Orders\Events\OrderConfirmed;
use App\Domain\Orders\Events\OrderDelivered;
use App\Domain\Orders\Events\OrderDeliveryFailed;
use App\Domain\Orders\Events\OrderShipped;
use App\Domain\Orders\Events\OrderStateEvent;
use App\Models\Customer;
use App\Models\Tenant;
use App\Support\Money;
use Lunar\DataTypes\Price;
use Lunar\Models\OrderAddress;
use Throwable;

class DispatchOrderNotification
{
    public function __construct(
        protected NotificationManager $notificationManager
    ) {}

    public function handle(OrderStateEvent $event): void
    {
        $order = $event->order;
        $eventType = match (get_class($event)) {
            OrderConfirmed::class => 'order_confirmed',
            OrderShipped::class => 'order_shipped',
            OrderDelivered::class => 'order_delivered',
            OrderCancelled::class => 'order_cancelled',
            OrderDeliveryFailed::class => 'order_cancelled',
            default => 'order_updated',
        };

        // Extract Customer and Recipient details
        /** @var OrderAddress|null $shippingAddress */
        $shippingAddress = $order->shippingAddress;
        /** @var Customer|\Lunar\Models\Customer|null $customer */
        $customer = $order->customer;

        $recipientPhone = $shippingAddress instanceof OrderAddress ? $shippingAddress->contact_phone : null;
        if (empty($recipientPhone) && $customer) {
            $recipientPhone = (string) ($customer->phone ?? '');
        }

        $recipientEmail = $shippingAddress instanceof OrderAddress ? $shippingAddress->contact_email : null;
        if (empty($recipientEmail) && $customer) {
            $recipientEmail = (string) ($customer->email ?? '');
        }

        $customerName = '';
        if ($shippingAddress instanceof OrderAddress) {
            $customerName = trim($shippingAddress->first_name.' '.$shippingAddress->last_name);
        }
        if (empty($customerName) && $customer) {
            $customerName = trim(($customer->first_name ?? '').' '.($customer->last_name ?? ''));
        }
        if (empty($customerName)) {
            $customerName = 'عميلنا العزيز';
        }

        /** @var mixed $rawTotal */
        $rawTotal = $order->total;
        $totalMinor = $rawTotal instanceof Price ? $rawTotal->value : (int) $rawTotal;
        $totalFormatted = Money::fromMinor($totalMinor)->format('ar');

        $tenant = function_exists('tenancy') && tenancy()->initialized ? tenancy()->tenant : null;
        $storeName = $tenant instanceof Tenant ? $tenant->name : 'متجرنا';

        $meta = $event->meta;

        $data = [
            'order_number' => (string) ($order->reference ?: $order->id),
            'customer_name' => $customerName,
            'order_total' => $totalFormatted,
            'store_name' => $storeName,
            'courier_name' => (string) ($meta['courier_name'] ?? 'مندوب الشحن'),
            'tracking_number' => (string) ($meta['tracking_number'] ?? ($order->reference ?: $order->id)),
            'tracking_url' => (string) ($meta['tracking_url'] ?? url("/account/orders/{$order->id}")),
            'reason' => $event->reasonCode ? $event->reasonCode->label() : 'تحديث الطلب',
        ];

        // Send SMS/WhatsApp if phone available (Non-blocking: inside try/catch)
        if (! empty($recipientPhone)) {
            try {
                $this->notificationManager->send(
                    channel: NotificationChannel::SMS,
                    recipient: (string) $recipientPhone,
                    eventType: $eventType,
                    data: $data,
                    notifiable: $order
                );
            } catch (Throwable) {
                // Logged internally, never rethrow to preserve transaction integrity
            }
        }

        // Send Email if email available
        if (! empty($recipientEmail)) {
            try {
                $this->notificationManager->send(
                    channel: NotificationChannel::EMAIL,
                    recipient: (string) $recipientEmail,
                    eventType: $eventType,
                    data: $data,
                    notifiable: $order
                );
            } catch (Throwable) {
                // Non-blocking
            }
        }
    }
}
