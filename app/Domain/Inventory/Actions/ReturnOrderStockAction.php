<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Actions;

use App\Models\InventoryMovement;
use Lunar\Models\Order;
use Lunar\Models\OrderLine;

class ReturnOrderStockAction
{
    public function __construct(
        protected readonly ReturnStockAction $returnStockAction
    ) {}

    public function execute(
        Order $order,
        bool $isSound = true,
        string $actorType = 'system',
        ?string $actorId = null,
        ?string $notes = null
    ): void {
        $orderReference = (string) $order->reference;

        $shipMovements = InventoryMovement::query()
            ->where('reference_type', 'order')
            ->where('reference_id', $orderReference)
            ->where('reason', 'ship')
            ->get();

        if ($shipMovements->isNotEmpty()) {
            foreach ($shipMovements as $movement) {
                $variantId = (int) $movement->product_variant_id;
                $quantity = abs((int) $movement->quantity_delta);
                $warehouseId = (int) $movement->warehouse_id;

                if ($quantity > 0) {
                    $this->returnStockAction->execute(
                        variantId: $variantId,
                        quantity: $quantity,
                        orderReference: $orderReference,
                        isSound: $isSound,
                        warehouseId: $warehouseId,
                        actorType: $actorType,
                        actorId: $actorId,
                        notes: $notes
                    );
                }
            }
        } else {
            foreach ($order->lines as $line) {
                /** @var OrderLine $line */
                $variantId = (int) $line->purchasable_id;
                $quantity = (int) $line->quantity;
                if ($variantId > 0 && $quantity > 0) {
                    $this->returnStockAction->execute(
                        variantId: $variantId,
                        quantity: $quantity,
                        orderReference: $orderReference,
                        isSound: $isSound,
                        warehouseId: null,
                        actorType: $actorType,
                        actorId: $actorId,
                        notes: $notes
                    );
                }
            }
        }
    }
}
