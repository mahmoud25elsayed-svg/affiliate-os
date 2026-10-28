<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Store;

use App\Domain\Inventory\Actions\AdjustStockAction;
use App\Domain\Webhooks\Services\WebhookDispatcher;
use App\Http\Controllers\Controller;
use App\Models\InventoryBalance;
use App\Models\Warehouse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Lunar\Models\ProductVariant;

class StoreInventoryApiController extends Controller
{
    public function __construct(
        protected AdjustStockAction $adjustStockAction,
        protected WebhookDispatcher $webhookDispatcher
    ) {}

    public function index(Request $request): JsonResponse
    {
        $perPage = min(max((int) $request->input('per_page', 20), 1), 100);

        $query = InventoryBalance::query()
            ->with(['variant.product', 'warehouse'])
            ->latest();

        if ($request->has('variant_id')) {
            $query->where('product_variant_id', (int) $request->input('variant_id'));
        }

        if ($request->has('warehouse_id')) {
            $query->where('warehouse_id', (int) $request->input('warehouse_id'));
        }

        $balances = $query->paginate($perPage);

        return response()->json([
            'data' => $balances->items(),
            'meta' => [
                'current_page' => $balances->currentPage(),
                'last_page' => $balances->lastPage(),
                'per_page' => $balances->perPage(),
                'total' => $balances->total(),
            ],
        ]);
    }

    public function adjust(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'variant_id' => ['required', 'integer'],
            'warehouse_id' => ['nullable', 'integer'],
            'new_on_hand' => ['required', 'integer', 'min:0'],
            'reason' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        /** @var ProductVariant $variant */
        $variant = ProductVariant::findOrFail($validated['variant_id']);

        $warehouseId = $validated['warehouse_id']
            ?? Warehouse::firstOrCreate(['name' => 'المخزن الرئيسي'], ['is_default' => true])->id;

        $balance = $this->adjustStockAction->execute(
            variantId: (int) $variant->id,
            warehouseId: (int) $warehouseId,
            newOnHand: (int) $validated['new_on_hand'],
            reason: $validated['reason'] ?? 'api_adjustment',
            notes: $validated['notes'] ?? 'Stock adjusted via API'
        );

        if ($balance->available <= 5) {
            $this->webhookDispatcher->dispatch('inventory.low', [
                'variant_id' => $variant->id,
                'sku' => $variant->sku,
                'warehouse_id' => $warehouseId,
                'on_hand' => $balance->on_hand,
                'available' => $balance->available,
            ]);
        }

        return response()->json([
            'message' => 'Inventory adjusted successfully.',
            'data' => $balance,
        ]);
    }
}
