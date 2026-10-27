<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Listeners;

use App\Domain\Inventory\Actions\ReserveOrderStockAction;
use App\Domain\Inventory\Exceptions\InsufficientStockException;
use App\Domain\Orders\DTOs\OrderActor;
use App\Domain\Orders\Enums\OrderStatus;
use App\Domain\Orders\Events\OrderConfirmed;
use App\Domain\Orders\Services\OrderTransitionService;
use App\Support\Enums\ReasonCode;
use Illuminate\Support\Facades\Log;

class OrderConfirmedStockListener
{
    public function __construct(
        protected readonly ReserveOrderStockAction $reserveOrderStockAction,
        protected readonly OrderTransitionService $transitionService
    ) {}

    public function handle(OrderConfirmed $event): void
    {
        $order = $event->order;

        try {
            $reserved = $this->reserveOrderStockAction->execute($order);

            if (! $reserved) {
                return;
            }

            // Successfully reserved all items -> transition to RESERVED
            $this->transitionService->transition(
                order: $order,
                to: OrderStatus::RESERVED,
                reason: null,
                actor: OrderActor::system(),
                meta: ['inventory_reserved' => true]
            );
        } catch (InsufficientStockException $e) {
            Log::warning('Order confirmed but stock reservation failed due to insufficient quantity.', [
                'order_id' => $order->id,
                'order_reference' => $order->reference,
                'variant_id' => $e->variantId,
                'requested' => $e->requestedQuantity,
                'available' => $e->availableQuantity,
            ]);

            // Transition to OUT_OF_STOCK
            $this->transitionService->transition(
                order: $order,
                to: OrderStatus::OUT_OF_STOCK,
                reason: ReasonCode::OUT_OF_STOCK,
                actor: OrderActor::system(),
                meta: [
                    'insufficient_variant_id' => $e->variantId,
                    'requested' => $e->requestedQuantity,
                    'available' => $e->availableQuantity,
                ],
                note: sprintf('فشل حجز المخزون للمتغير #%d: المطلوب %d، المتاح %d.', $e->variantId, $e->requestedQuantity, $e->availableQuantity)
            );
        }
    }
}
