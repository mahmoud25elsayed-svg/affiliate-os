<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Actions;

use App\Domain\Inventory\Services\StockSyncService;
use App\Models\InventoryBalance;
use App\Models\InventoryMovement;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ShipStockAction
{
    public function __construct(
        protected readonly StockSyncService $syncService
    ) {}

    public function execute(
        int $variantId,
        int $quantity,
        string $orderReference,
        ?int $warehouseId = null,
        string $actorType = 'system',
        ?string $actorId = null
    ): InventoryBalance {
        if ($quantity <= 0) {
            throw new InvalidArgumentException('Ship quantity must be greater than zero.');
        }

        return DB::transaction(function () use (
            $variantId,
            $quantity,
            $orderReference,
            $warehouseId,
            $actorType,
            $actorId
        ): InventoryBalance {
            $referenceKey = sprintf('order:%s:variant:%d:ship', $orderReference, $variantId);

            // Check idempotency: If this shipment was already recorded, return
            $existing = InventoryMovement::where('reference_key', $referenceKey)->first();
            if ($existing) {
                return InventoryBalance::where('warehouse_id', $existing->warehouse_id)
                    ->where('product_variant_id', $variantId)
                    ->firstOrFail();
            }

            // Find warehouse where reservation movement took place, or given warehouse
            $targetWarehouseId = $warehouseId;
            if ($targetWarehouseId === null) {
                $reserveMovement = InventoryMovement::query()
                    ->where('reference_type', 'order')
                    ->where('reference_id', $orderReference)
                    ->where('product_variant_id', $variantId)
                    ->where('reason', 'reserve')
                    ->first();

                if ($reserveMovement) {
                    $targetWarehouseId = (int) $reserveMovement->warehouse_id;
                }
            }

            $query = InventoryBalance::query()
                ->where('product_variant_id', $variantId);

            if ($targetWarehouseId !== null) {
                $query->where('warehouse_id', $targetWarehouseId);
            }

            /** @var InventoryBalance|null $balance */
            $balance = $query->lockForUpdate()->first();

            if (! $balance) {
                $balance = new InventoryBalance([
                    'warehouse_id' => $targetWarehouseId ?? 1,
                    'product_variant_id' => $variantId,
                    'on_hand' => 0,
                    'reserved' => 0,
                ]);
            }

            $deductOnHand = min($balance->on_hand, $quantity);
            $deductReserved = min($balance->reserved, $quantity);

            $balance->on_hand = max(0, $balance->on_hand - $quantity);
            $balance->reserved = max(0, $balance->reserved - $quantity);
            $balance->save();

            InventoryMovement::create([
                'warehouse_id' => $balance->warehouse_id,
                'product_variant_id' => $variantId,
                'quantity_delta' => -$deductOnHand,
                'reserved_delta' => -$deductReserved,
                'reason' => 'ship',
                'reference_type' => 'order',
                'reference_id' => $orderReference,
                'reference_key' => $referenceKey,
                'actor_type' => $actorType,
                'actor_id' => $actorId,
            ]);

            $this->syncService->syncVariantStock($variantId);

            return $balance;
        });
    }
}
