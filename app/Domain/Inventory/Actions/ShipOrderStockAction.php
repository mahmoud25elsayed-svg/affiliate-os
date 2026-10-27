<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Actions;

use App\Models\InventoryMovement;
use Lunar\Models\Order;
use Lunar\Models\OrderLine;

class ShipOrderStockAction
{
    public function __construct(
        protected readonly ShipStockAction $shipStockAction
    ) {}

    public function execute(
        Order $order,
        string $actorType = 'system',
        ?string $actorId = null
    ): void {
        $orderReference = (string) $order->reference;

        $reserveMovements = InventoryMovement::query()
            ->where('reference_type', 'order')
            ->where('reference_id', $orderReference)
            ->where('reason', 'reserve')
            ->get();

        if ($reserveMovements->isNotEmpty()) {
            foreach ($reserveMovements as $movement) {
                $variantId = (int) $movement->product_variant_id;
                $quantity = (int) $movement->reserved_delta;
                $warehouseId = (int) $movement->warehouse_id;

                if ($quantity > 0) {
                    $this->shipStockAction->execute(
                        variantId: $variantId,
                        quantity: $quantity,
                        orderReference: $orderReference,
                        warehouseId: $warehouseId,
                        actorType: $actorType,
                        actorId: $actorId
                    );
                }
            }
        } else {
            foreach ($order->lines as $line) {
                /** @var OrderLine $line */
                $variantId = (int) $line->purchasable_id;
                $quantity = (int) $line->quantity;
                if ($variantId > 0 && $quantity > 0) {
                    $this->shipStockAction->execute(
                        variantId: $variantId,
                        quantity: $quantity,
                        orderReference: $orderReference,
                        warehouseId: null,
                        actorType: $actorType,
                        actorId: $actorId
                    );
                }
            }
        }
    }
}
