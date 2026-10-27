<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Actions;

use App\Domain\Inventory\Services\StockSyncService;
use App\Models\InventoryBalance;
use App\Models\InventoryMovement;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ReturnStockAction
{
    public function __construct(
        protected readonly StockSyncService $syncService
    ) {}

    public function execute(
        int $variantId,
        int $quantity,
        string $orderReference,
        bool $isSound = true,
        ?int $warehouseId = null,
        string $actorType = 'system',
        ?string $actorId = null,
        ?string $notes = null
    ): InventoryBalance {
        if ($quantity <= 0) {
            throw new InvalidArgumentException('Return quantity must be greater than zero.');
        }

        return DB::transaction(function () use (
            $variantId,
            $quantity,
            $orderReference,
            $isSound,
            $warehouseId,
            $actorType,
            $actorId,
            $notes
        ): InventoryBalance {
            $referenceKey = sprintf('order:%s:variant:%d:return', $orderReference, $variantId);

            // Check idempotency: If this return was already processed, return
            $existing = InventoryMovement::where('reference_key', $referenceKey)->first();
            if ($existing) {
                return InventoryBalance::where('warehouse_id', $existing->warehouse_id)
                    ->where('product_variant_id', $variantId)
                    ->firstOrFail();
            }

            // Find warehouse where shipping movement took place, or given warehouse, or default
            $targetWarehouseId = $warehouseId;
            if ($targetWarehouseId === null) {
                $shipMovement = InventoryMovement::query()
                    ->where('reference_type', 'order')
                    ->where('reference_id', $orderReference)
                    ->where('product_variant_id', $variantId)
                    ->where('reason', 'ship')
                    ->first();

                if ($shipMovement) {
                    $targetWarehouseId = (int) $shipMovement->warehouse_id;
                } else {
                    $defaultWarehouse = Warehouse::query()->where('is_active', true)->where('is_default', true)->first();
                    $targetWarehouseId = $defaultWarehouse ? (int) $defaultWarehouse->id : 1;
                }
            }

            /** @var InventoryBalance|null $balance */
            $balance = InventoryBalance::query()
                ->where('warehouse_id', $targetWarehouseId)
                ->where('product_variant_id', $variantId)
                ->lockForUpdate()
                ->first();

            if (! $balance) {
                $balance = new InventoryBalance([
                    'warehouse_id' => $targetWarehouseId,
                    'product_variant_id' => $variantId,
                    'on_hand' => 0,
                    'reserved' => 0,
                ]);
            }

            $addedOnHand = $isSound ? $quantity : 0;
            if ($addedOnHand > 0) {
                $balance->on_hand += $addedOnHand;
                $balance->save();
            }

            InventoryMovement::create([
                'warehouse_id' => $balance->warehouse_id,
                'product_variant_id' => $variantId,
                'quantity_delta' => $addedOnHand,
                'reserved_delta' => 0,
                'reason' => $isSound ? 'return' : 'return_damaged',
                'reference_type' => 'order',
                'reference_id' => $orderReference,
                'reference_key' => $referenceKey,
                'actor_type' => $actorType,
                'actor_id' => $actorId,
                'notes' => $notes,
            ]);

            if ($addedOnHand > 0) {
                $this->syncService->syncVariantStock($variantId);
            }

            return $balance;
        });
    }
}
