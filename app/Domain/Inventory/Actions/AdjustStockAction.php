<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Actions;

use App\Domain\Inventory\Services\StockSyncService;
use App\Models\InventoryBalance;
use App\Models\InventoryMovement;
use DomainException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class AdjustStockAction
{
    public function __construct(
        protected readonly StockSyncService $syncService
    ) {}

    public function execute(
        int $variantId,
        int $warehouseId,
        int $newOnHand,
        string $reason = 'adjust',
        ?string $referenceType = 'adjustment',
        ?string $referenceId = null,
        ?string $notes = null,
        string $actorType = 'staff',
        ?string $actorId = null
    ): InventoryBalance {
        if ($newOnHand < 0) {
            throw new InvalidArgumentException('On-hand quantity cannot be negative.');
        }

        return DB::transaction(function () use (
            $variantId,
            $warehouseId,
            $newOnHand,
            $reason,
            $referenceType,
            $referenceId,
            $notes,
            $actorType,
            $actorId
        ): InventoryBalance {
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
            }

            if ($newOnHand < $balance->reserved) {
                throw new DomainException(sprintf(
                    'Cannot adjust on-hand stock to %d because %d units are currently reserved for pending orders.',
                    $newOnHand,
                    $balance->reserved
                ));
            }

            $delta = $newOnHand - $balance->on_hand;
            $balance->on_hand = $newOnHand;
            $balance->save();

            if ($delta !== 0) {
                InventoryMovement::create([
                    'warehouse_id' => $warehouseId,
                    'product_variant_id' => $variantId,
                    'quantity_delta' => $delta,
                    'reserved_delta' => 0,
                    'reason' => $reason,
                    'reference_type' => $referenceType,
                    'reference_id' => $referenceId,
                    'actor_type' => $actorType,
                    'actor_id' => $actorId,
                    'notes' => $notes,
                ]);
            }

            $this->syncService->syncVariantStock($variantId);

            return $balance;
        });
    }
}
