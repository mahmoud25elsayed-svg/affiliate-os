<?php

declare(strict_types=1);

namespace App\Domain\Orders\Services;

use App\Domain\Orders\DTOs\OrderActor;
use App\Domain\Orders\Enums\OrderStatus;
use App\Domain\Orders\Events\OrderCancelled;
use App\Domain\Orders\Events\OrderConfirmed;
use App\Domain\Orders\Events\OrderDelivered;
use App\Domain\Orders\Events\OrderDeliveryFailed;
use App\Domain\Orders\Events\OrderPacked;
use App\Domain\Orders\Events\OrderReturned;
use App\Domain\Orders\Events\OrderSettled;
use App\Domain\Orders\Events\OrderShipped;
use App\Domain\Orders\Events\OrderStateEvent;
use App\Domain\Orders\Events\OrderSubmitted;
use App\Domain\Orders\Exceptions\InvalidOrderTransitionException;
use App\Domain\Orders\Exceptions\ReasonCodeRequiredException;
use App\Models\OrderEvent;
use App\Support\Enums\ReasonCode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Lunar\Models\Order;

class OrderTransitionService
{
    /**
     * Guard flag to enforce that order status cannot be modified outside this service.
     */
    public static bool $isTransitioning = false;

    /**
     * Transition an order to a new status.
     *
     * @param  array<string, mixed>  $meta
     *
     * @throws InvalidOrderTransitionException
     * @throws ReasonCodeRequiredException
     */
    public function transition(
        Order $order,
        OrderStatus $to,
        ?ReasonCode $reason = null,
        ?OrderActor $actor = null,
        array $meta = [],
        ?string $note = null
    ): Order {
        $actor ??= OrderActor::system();

        $from = OrderStatus::tryFrom((string) $order->status) ?? OrderStatus::SUBMITTED;

        // Idempotency: duplicate transition does not repeat event or create duplicate order_events record
        if ($from === $to) {
            return $order;
        }

        // Validate state machine transition & actor permission
        if (! OrderTransitions::canTransition($from, $to, $actor)) {
            throw new InvalidOrderTransitionException($from, $to, $actor);
        }

        // Validate ReasonCode requirement
        if (OrderTransitions::requiresReason($from, $to) && $reason === null) {
            throw new ReasonCodeRequiredException($from, $to);
        }

        $eventToDispatch = null;

        DB::transaction(function () use ($order, $from, $to, $reason, $actor, $meta, $note, &$eventToDispatch): void {
            // Pessimistic row locking
            /** @var Order $lockedOrder */
            $lockedOrder = Order::query()
                ->where('id', $order->id)
                ->lockForUpdate()
                ->firstOrFail();

            $currentStatus = OrderStatus::tryFrom((string) $lockedOrder->status) ?? OrderStatus::SUBMITTED;

            // Re-verify after obtaining lock
            if ($currentStatus === $to) {
                return;
            }

            if ($currentStatus !== $from && ! OrderTransitions::canTransition($currentStatus, $to, $actor)) {
                throw new InvalidOrderTransitionException($currentStatus, $to, $actor);
            }

            // Update status under transition guard
            self::$isTransitioning = true;

            try {
                $lockedOrder->status = $to->value;
                $lockedOrder->save();
            } finally {
                self::$isTransitioning = false;
            }

            $order->status = $to->value;

            $tenantId = tenant('id');
            $tenantIdString = is_string($tenantId) ? $tenantId : (string) ($lockedOrder->getAttribute('tenant_id') ?? 'default');

            // Record append-only event
            $orderEvent = OrderEvent::create([
                'tenant_id' => $tenantIdString,
                'order_id' => $lockedOrder->id,
                'from_status' => $from->value,
                'to_status' => $to->value,
                'reason_code' => $reason,
                'actor_type' => $actor->type,
                'actor_id' => $actor->id,
                'actor_name' => $actor->name,
                'note' => $note,
                'meta' => $meta,
                'created_at' => now(),
            ]);

            $eventToDispatch = $this->resolveDomainEvent($to, $lockedOrder, $orderEvent, $reason, $actor, $meta);
        });

        if ($eventToDispatch !== null) {
            Event::dispatch($eventToDispatch);
        }

        return $order->fresh() ?? $order;
    }

    /**
     * Map target status to the corresponding domain event.
     *
     * @param  array<string, mixed>  $meta
     */
    protected function resolveDomainEvent(
        OrderStatus $to,
        Order $order,
        OrderEvent $orderEvent,
        ?ReasonCode $reason,
        OrderActor $actor,
        array $meta
    ): ?OrderStateEvent {
        return match ($to) {
            OrderStatus::SUBMITTED => new OrderSubmitted($order, $orderEvent, $reason, $actor, $meta),
            OrderStatus::CONFIRMED => new OrderConfirmed($order, $orderEvent, $reason, $actor, $meta),
            OrderStatus::CANCELLED_BY_CUSTOMER,
            OrderStatus::CANCELLED_NO_ANSWER => new OrderCancelled($order, $orderEvent, $reason, $actor, $meta),
            OrderStatus::PACKED => new OrderPacked($order, $orderEvent, $reason, $actor, $meta),
            OrderStatus::SHIPPED,
            OrderStatus::OUT_FOR_DELIVERY => new OrderShipped($order, $orderEvent, $reason, $actor, $meta),
            OrderStatus::DELIVERED => new OrderDelivered($order, $orderEvent, $reason, $actor, $meta),
            OrderStatus::DELIVERY_FAILED => new OrderDeliveryFailed($order, $orderEvent, $reason, $actor, $meta),
            OrderStatus::RETURNED,
            OrderStatus::RETURNING,
            OrderStatus::RETURN_REQUESTED => new OrderReturned($order, $orderEvent, $reason, $actor, $meta),
            OrderStatus::SETTLED => new OrderSettled($order, $orderEvent, $reason, $actor, $meta),
            default => null,
        };
    }
}
