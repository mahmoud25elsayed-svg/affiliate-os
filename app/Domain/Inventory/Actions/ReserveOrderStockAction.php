<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Actions;

use App\Domain\Inventory\Exceptions\InsufficientStockException;
use App\Domain\Inventory\Services\StockSyncService;
use App\Models\InventoryBalance;
use App\Models\InventoryMovement;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;
use Lunar\Models\Order;
use Lunar\Models\OrderLine;

class ReserveOrderStockAction
{
    public function __construct(
        protected readonly StockSyncService $syncService
    ) {}

    /**
     * Reserve all stock needed for an entire order atomically and deadlock-free.
     *
     * @throws InsufficientStockException
     */
    public function execute(
        Order $order,
        ?int $warehouseId = null,
        string $actorType = 'system',
        ?string $actorId = null
    ): bool {
        // Collect required quantities per variant
        $requirements = [];
        foreach ($order->lines as $line) {
            /** @var OrderLine $line */
            $variantId = (int) $line->purchasable_id;
            $quantity = (int) $line->quantity;
            if ($variantId <= 0 || $quantity <= 0) {
                continue;
            }

            $requirements[$variantId] = ($requirements[$variantId] ?? 0) + $quantity;
        }

        if (empty($requirements)) {
            return false;
        }

        // Sort variant IDs in ascending order to prevent deadlocks across concurrent orders
        ksort($requirements, SORT_NUMERIC);

        return DB::transaction(function () use (
            $requirements,
            $order,
            $warehouseId,
            $actorType,
            $actorId
        ): bool {
            $orderReference = (string) $order->reference;
            $balancesToUpdate = [];

            // Find default warehouse if not specified
            $defaultWarehouse = null;
            if ($warehouseId === null) {
                $defaultWarehouse = Warehouse::query()->where('is_active', true)->where('is_default', true)->first();
            }

            // Phase 1: Lock all balances in deterministic order and verify stock sufficiency
            foreach ($requirements as $variantId => $neededQuantity) {
                $referenceKey = sprintf('order:%s:variant:%d:reserve', $orderReference, $variantId);

                // If already reserved idempotently, skip
                if (InventoryMovement::where('reference_key', $referenceKey)->exists()) {
                    continue;
                }

                $query = InventoryBalance::query()
                    ->where('product_variant_id', $variantId)
                    ->whereHas('warehouse', function ($q) {
                        $q->where('is_active', true);
                    });

                if ($warehouseId !== null) {
                    $query->where('warehouse_id', $warehouseId);
                } elseif ($defaultWarehouse) {
                    $query->orderByRaw('CASE WHEN warehouse_id = '.(int) $defaultWarehouse->id.' THEN 0 ELSE 1 END');
                }

                /** @var InventoryBalance|null $balance */
                $balance = $query->lockForUpdate()->first();

                if (! $balance || $balance->available < $neededQuantity) {
                    throw new InsufficientStockException(
                        variantId: $variantId,
                        requestedQuantity: $neededQuantity,
                        availableQuantity: $balance ? $balance->available : 0
                    );
                }

                $balancesToUpdate[] = [
                    'balance' => $balance,
                    'variant_id' => $variantId,
                    'quantity' => $neededQuantity,
                    'reference_key' => $referenceKey,
                ];
            }

            // Phase 2: Apply reservations and append audit movements
            foreach ($balancesToUpdate as $item) {
                /** @var InventoryBalance $balance */
                $balance = $item['balance'];
                $variantId = $item['variant_id'];
                $qty = $item['quantity'];
                $refKey = $item['reference_key'];

                $balance->reserved += $qty;
                $balance->save();

                InventoryMovement::create([
                    'warehouse_id' => $balance->warehouse_id,
                    'product_variant_id' => $variantId,
                    'quantity_delta' => 0,
                    'reserved_delta' => $qty,
                    'reason' => 'reserve',
                    'reference_type' => 'order',
                    'reference_id' => $orderReference,
                    'reference_key' => $refKey,
                    'actor_type' => $actorType,
                    'actor_id' => $actorId,
                ]);

                $this->syncService->syncVariantStock($variantId);
            }

            return true;
        });
    }
}
