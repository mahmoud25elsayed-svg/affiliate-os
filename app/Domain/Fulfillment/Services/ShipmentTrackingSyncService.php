<?php

declare(strict_types=1);

namespace App\Domain\Fulfillment\Services;

use App\Domain\Fulfillment\Enums\ShipmentStatus;
use App\Domain\Orders\DTOs\OrderActor;
use App\Domain\Orders\Enums\OrderStatus;
use App\Domain\Orders\Services\OrderTransitionService;
use App\Models\CourierAccount;
use App\Models\OrderEvent;
use App\Models\Shipment;
use App\Support\Enums\ReasonCode;
use App\Support\Money;
use Lunar\Models\Order;

class ShipmentTrackingSyncService
{
    public function __construct(
        protected OrderTransitionService $transitionService
    ) {}

    /**
     * Synchronize a shipment status update and cascade it to the order state machine and timelines.
     */
    public function syncStatus(Shipment $shipment, ShipmentStatus $newStatus, ?string $note = null): void
    {
        $oldStatus = $shipment->status;
        if ($oldStatus === $newStatus) {
            return;
        }

        // 1. Update Shipment Model & Status History
        $shipment->recordStatusChange($newStatus, $note);

        /** @var Order|null $order */
        $order = $shipment->order;
        if (! $order instanceof Order) {
            return;
        }

        $currentOrderStatus = OrderStatus::tryFrom((string) $order->status);
        $actor = OrderActor::system();

        // 2. Cascade status to Order State Machine
        switch ($newStatus) {
            case ShipmentStatus::OUT_FOR_DELIVERY:
                if ($currentOrderStatus === OrderStatus::SHIPPED || $currentOrderStatus === OrderStatus::DELIVERY_FAILED) {
                    $this->transitionService->transition(
                        order: $order,
                        to: OrderStatus::OUT_FOR_DELIVERY,
                        reason: null,
                        actor: $actor,
                        meta: ['tracking_number' => $shipment->tracking_number],
                        note: 'الشحنة خرجت للتسليم مع مندوب الشحن.'
                    );
                }
                break;

            case ShipmentStatus::DELIVERED:
                if ($currentOrderStatus === OrderStatus::SHIPPED) {
                    $this->transitionService->transition(order: $order, to: OrderStatus::OUT_FOR_DELIVERY, reason: null, actor: $actor);
                    $currentOrderStatus = OrderStatus::OUT_FOR_DELIVERY;
                }

                if ($currentOrderStatus === OrderStatus::OUT_FOR_DELIVERY) {
                    $this->transitionService->transition(
                        order: $order,
                        to: OrderStatus::DELIVERED,
                        reason: null,
                        actor: $actor,
                        meta: [
                            'tracking_number' => $shipment->tracking_number,
                            'delivered_at' => (string) $shipment->delivered_at,
                        ],
                        note: 'تم تسليم الشحنة للعميل بنجاح وتحصيل المبلغ المطلوب ('.number_format($shipment->cod_amount_minor / 100, 2).' '.Money::symbol().').'
                    );
                }
                break;

            case ShipmentStatus::FAILED_ATTEMPT:
                if ($currentOrderStatus === OrderStatus::OUT_FOR_DELIVERY) {
                    $this->transitionService->transition(
                        order: $order,
                        to: OrderStatus::DELIVERY_FAILED,
                        reason: ReasonCode::CUSTOMER_UNREACHABLE,
                        actor: $actor,
                        meta: ['tracking_number' => $shipment->tracking_number],
                        note: 'فشل محاولة تسليم الشحنة: '.($note ?? 'العميل لم يستجب للمندوب')
                    );
                }
                break;

            case ShipmentStatus::RETURNING:
                if ($currentOrderStatus === OrderStatus::SHIPPED || $currentOrderStatus === OrderStatus::OUT_FOR_DELIVERY || $currentOrderStatus === OrderStatus::DELIVERY_FAILED) {
                    $this->transitionService->transition(
                        order: $order,
                        to: OrderStatus::RETURNING,
                        reason: ReasonCode::REFUSED_AT_DOORSTEP,
                        actor: $actor,
                        meta: ['tracking_number' => $shipment->tracking_number],
                        note: 'الشحنة قيد الإرجاع للمتجر: '.($note ?? 'رفض العميل الاستلام عند الباب')
                    );
                }
                break;

            case ShipmentStatus::RETURNED:
                if ($currentOrderStatus === OrderStatus::RETURNING) {
                    $this->transitionService->transition(
                        order: $order,
                        to: OrderStatus::RETURNED,
                        reason: null,
                        actor: $actor,
                        meta: ['tracking_number' => $shipment->tracking_number],
                        note: 'تم استلام الشحنة المرتجعة في المخزن والتحقق من سلامة المنتجات.'
                    );
                }
                break;

            default:
                // Other statuses like PICKED_UP, IN_TRANSIT: Log timeline info event if order is already shipped
                $courierAccount = $shipment->courierAccount;
                $courierName = $courierAccount instanceof CourierAccount ? $courierAccount->name : 'شركة الشحن';

                $tenantId = tenant('id');
                $tenantIdString = is_string($tenantId) ? $tenantId : (string) ($order->getAttribute('tenant_id') ?? 'default');

                OrderEvent::create([
                    'tenant_id' => $tenantIdString,
                    'order_id' => $order->id,
                    'from_status' => (string) $order->status,
                    'to_status' => (string) $order->status,
                    'actor_type' => 'system',
                    'actor_name' => $courierName,
                    'note' => "تحديث مسار الشحنة ({$shipment->tracking_number}): {$newStatus->label()}. ".($note ?? ''),
                    'created_at' => now(),
                ]);
                break;
        }
    }
}
