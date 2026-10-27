<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Actions;

use App\Domain\Inventory\Exceptions\InsufficientStockException;
use App\Domain\Inventory\Services\StockSyncService;
use App\Models\InventoryBalance;
use App\Models\InventoryMovement;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ReserveStockAction
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
            throw new InvalidArgumentException('Reservation quantity must be greater than zero.');
        }

        return DB::transaction(function () use (
            $variantId,
            $quantity,
            $orderReference,
            $warehouseId,
            $actorType,
            $actorId
        ): InventoryBalance {
            $referenceKey = sprintf('order:%s:variant:%d:reserve', $orderReference, $variantId);

            // Check idempotency: If this reservation was already made, return current balance
            $existing = InventoryMovement::where('reference_key', $referenceKey)->first();
            if ($existing) {
                return InventoryBalance::where('warehouse_id', $existing->warehouse_id)
                    ->where('product_variant_id', $variantId)
                    ->firstOrFail();
            }

            // Find targeted warehouse balance
            $balanceQuery = InventoryBalance::query()
                ->where('product_variant_id', $variantId)
                ->whereHas('warehouse', function ($q) {
                    $q->where('is_active', true);
                });

            if ($warehouseId !== null) {
                $balanceQuery->where('warehouse_id', $warehouseId);
            } else {
                // Pick default warehouse if available, or any active warehouse with sufficient stock
                $defaultWarehouse = Warehouse::query()->where('is_active', true)->where('is_default', true)->first();
                if ($defaultWarehouse) {
                    $balanceQuery->orderByRaw('CASE WHEN warehouse_id = '.(int) $defaultWarehouse->id.' THEN 0 ELSE 1 END');
                }
            }

            /** @var InventoryBalance|null $balance */
            $balance = $balanceQuery->lockForUpdate()->first();

            if (! $balance) {
                throw new InsufficientStockException(
                    variantId: $variantId,
                    requestedQuantity: $quantity,
                    availableQuantity: 0
                );
            }

            if ($balance->available < $quantity) {
                throw new InsufficientStockException(
                    variantId: $variantId,
                    requestedQuantity: $quantity,
                    availableQuantity: $balance->available
                );
            }

            $balance->reserved += $quantity;
            $balance->save();

            InventoryMovement::create([
                'warehouse_id' => $balance->warehouse_id,
                'product_variant_id' => $variantId,
                'quantity_delta' => 0,
                'reserved_delta' => $quantity,
                'reason' => 'reserve',
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
