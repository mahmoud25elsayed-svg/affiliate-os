<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Actions;

use App\Domain\Inventory\Exceptions\InsufficientStockException;
use App\Domain\Inventory\Services\StockSyncService;
use App\Models\InventoryBalance;
use App\Models\InventoryMovement;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class TransferStockAction
{
    public function __construct(
        protected readonly StockSyncService $syncService
    ) {}

    public function execute(
        int $variantId,
        int $quantity,
        int $fromWarehouseId,
        int $toWarehouseId,
        ?string $referenceId = null,
        ?string $notes = null,
        string $actorType = 'staff',
        ?string $actorId = null
    ): void {
        if ($quantity <= 0) {
            throw new InvalidArgumentException('Transfer quantity must be greater than zero.');
        }

        if ($fromWarehouseId === $toWarehouseId) {
            throw new InvalidArgumentException('Source and destination warehouses must be different.');
        }

        DB::transaction(function () use (
            $variantId,
            $quantity,
            $fromWarehouseId,
            $toWarehouseId,
            $referenceId,
            $notes,
            $actorType,
            $actorId
        ): void {
            // Lock in ascending warehouse_id order to prevent deadlocks
            $firstWarehouseId = min($fromWarehouseId, $toWarehouseId);
            $secondWarehouseId = max($fromWarehouseId, $toWarehouseId);

            $firstBalance = $this->getOrCreateBalance($firstWarehouseId, $variantId);
            $secondBalance = $this->getOrCreateBalance($secondWarehouseId, $variantId);

            $fromBalance = ($fromWarehouseId === $firstWarehouseId) ? $firstBalance : $secondBalance;
            $toBalance = ($toWarehouseId === $firstWarehouseId) ? $firstBalance : $secondBalance;

            if ($fromBalance->available < $quantity) {
                throw new InsufficientStockException(
                    variantId: $variantId,
                    requestedQuantity: $quantity,
                    availableQuantity: $fromBalance->available
                );
            }

            $fromBalance->on_hand -= $quantity;
            $fromBalance->save();

            $toBalance->on_hand += $quantity;
            $toBalance->save();

            $refId = $referenceId ?? ('TRF-'.uniqid());

            // Movement Out
            InventoryMovement::create([
                'warehouse_id' => $fromWarehouseId,
                'product_variant_id' => $variantId,
                'quantity_delta' => -$quantity,
                'reserved_delta' => 0,
                'reason' => 'transfer_out',
                'reference_type' => 'transfer',
                'reference_id' => $refId,
                'actor_type' => $actorType,
                'actor_id' => $actorId,
                'notes' => $notes,
            ]);

            // Movement In
            InventoryMovement::create([
                'warehouse_id' => $toWarehouseId,
                'product_variant_id' => $variantId,
                'quantity_delta' => $quantity,
                'reserved_delta' => 0,
                'reason' => 'transfer_in',
                'reference_type' => 'transfer',
                'reference_id' => $refId,
                'actor_type' => $actorType,
                'actor_id' => $actorId,
                'notes' => $notes,
            ]);

            $this->syncService->syncVariantStock($variantId);
        });
    }

    protected function getOrCreateBalance(int $warehouseId, int $variantId): InventoryBalance
    {
        /** @var InventoryBalance|null $balance */
        $balance = InventoryBalance::query()
            ->where('warehouse_id', $warehouseId)
            ->where('product_variant_id', $variantId)
            ->lockForUpdate()
            ->first();

        if (! $balance) {
            $balance = new InventoryBalance([
                'warehouse_id' => $warehouseId,
                'product_variant_id' => $variantId,
                'on_hand' => 0,
                'reserved' => 0,
            ]);
            $balance->save();
        }

        return $balance;
    }
}
