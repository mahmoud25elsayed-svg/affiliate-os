<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Actions;

use App\Domain\Inventory\Services\StockSyncService;
use App\Models\InventoryBalance;
use App\Models\InventoryMovement;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class InboundStockAction
{
    public function __construct(
        protected readonly StockSyncService $syncService
    ) {}

    public function execute(
        int $variantId,
        int $quantity,
        int $warehouseId,
        ?string $referenceType = 'supplier_receipt',
        ?string $referenceId = null,
        ?string $referenceKey = null,
        ?string $actorType = 'staff',
        ?string $actorId = null,
        ?string $notes = null
    ): InventoryBalance {
        if ($quantity <= 0) {
            throw new InvalidArgumentException('Inbound quantity must be greater than zero.');
        }

        return DB::transaction(function () use (
            $variantId,
            $quantity,
            $warehouseId,
            $referenceType,
            $referenceId,
            $referenceKey,
            $actorType,
            $actorId,
            $notes
        ): InventoryBalance {
            // Check idempotency if reference_key provided
            if ($referenceKey !== null) {
                $existing = InventoryMovement::where('reference_key', $referenceKey)->first();
                if ($existing) {
                    return InventoryBalance::where('warehouse_id', $warehouseId)
                        ->where('product_variant_id', $variantId)
                        ->firstOrFail();
                }
            }

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

            $balance->on_hand += $quantity;
            $balance->save();

            InventoryMovement::create([
                'warehouse_id' => $warehouseId,
                'product_variant_id' => $variantId,
                'quantity_delta' => $quantity,
                'reserved_delta' => 0,
                'reason' => 'inbound',
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'reference_key' => $referenceKey,
                'actor_type' => $actorType ?? 'staff',
                'actor_id' => $actorId,
                'notes' => $notes,
            ]);

            $this->syncService->syncVariantStock($variantId);

            return $balance;
        });
    }
}
