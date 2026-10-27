<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Services;

use App\Models\InventoryBalance;
use App\Models\WholesalePrice;
use Lunar\Models\ProductVariant;

class StockSyncService
{
    /**
     * Synchronize available inventory to Lunar's ProductVariant::stock
     * and handle auto-pausing/re-activating products based on available stock.
     */
    public function syncVariantStock(int $variantId): int
    {
        // Calculate total available stock across active warehouses for this variant
        $totalAvailable = (int) InventoryBalance::query()
            ->join('warehouses', 'inventory_balances.warehouse_id', '=', 'warehouses.id')
            ->where('warehouses.is_active', true)
            ->where('inventory_balances.product_variant_id', $variantId)
            ->selectRaw('COALESCE(SUM(CASE WHEN (inventory_balances.on_hand - inventory_balances.reserved) > 0 THEN (inventory_balances.on_hand - inventory_balances.reserved) ELSE 0 END), 0) as total_available')
            ->value('total_available');

        // Update Lunar ProductVariant stock column
        ProductVariant::query()
            ->where('id', $variantId)
            ->update(['stock' => $totalAvailable]);

        // Manage auto-pause / auto-resume on WholesalePrice
        $pricing = WholesalePrice::query()
            ->where('product_variant_id', $variantId)
            ->first();

        if ($pricing) {
            if ($totalAvailable <= 0 && $pricing->status === WholesalePrice::STATUS_ACTIVE) {
                $pricing->update([
                    'status' => WholesalePrice::STATUS_PAUSED,
                    'pause_reason' => 'out_of_stock',
                ]);
            } elseif ($totalAvailable > 0 && $pricing->status === WholesalePrice::STATUS_PAUSED && $pricing->pause_reason === 'out_of_stock') {
                $pricing->update([
                    'status' => WholesalePrice::STATUS_ACTIVE,
                    'pause_reason' => null,
                ]);
            }
        }

        return $totalAvailable;
    }
}
