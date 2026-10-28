<?php

declare(strict_types=1);

namespace App\Http\Controllers\Store;

use App\Domain\Finance\DTOs\EntryData;
use App\Domain\Finance\DTOs\LedgerLineData;
use App\Domain\Finance\Services\AccountResolver;
use App\Domain\Finance\Services\Ledger;
use App\Http\Controllers\Controller;
use App\Models\Supplier;
use App\Models\SupplierBatch;
use App\Models\WholesalePrice;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Lunar\Models\ProductVariant;

class StoreSupplierController extends Controller
{
    public function index(): View
    {
        $suppliers = Supplier::query()
            ->withCount(['batches', 'wholesalePrices'])
            ->latest()
            ->paginate(15);

        return view('store.suppliers.index', [
            'suppliers' => $suppliers,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'company_name' => ['nullable', 'string', 'max:150'],
            'phone' => ['required', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:150'],
            'tax_id' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:255'],
            'default_lead_time_days' => ['required', 'integer', 'min:1', 'max:30'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        Supplier::create($validated);

        return redirect()->route('store.suppliers.index')
            ->with('success', 'تم إضافة المورد بنجاح.');
    }

    public function show(Supplier $supplier, AccountResolver $accountResolver): View
    {
        $supplier->load(['batches.variant.product', 'wholesalePrices.variant.product']);

        $payableAccount = $accountResolver->getSupplierPayableAccount($supplier);
        $payableBalanceMinor = $payableAccount->balance_minor;

        $variants = ProductVariant::with(['wholesalePrice', 'product'])->get();

        return view('store.suppliers.show', [
            'supplier' => $supplier,
            'payableBalanceMinor' => $payableBalanceMinor,
            'variants' => $variants,
        ]);
    }

    public function storeBatch(
        Request $request,
        Supplier $supplier,
        AccountResolver $accountResolver,
        Ledger $ledger
    ): RedirectResponse {
        $validated = $request->validate([
            'product_variant_id' => ['required', 'integer', 'exists:lunar_product_variants,id'],
            'quantity_supplied' => ['required', 'integer', 'min:1'],
            'unit_cost' => ['required', 'numeric', 'min:0.01'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $unitCostMinor = (int) round(((float) $validated['unit_cost']) * 100);
        $quantity = (int) $validated['quantity_supplied'];
        $totalCostMinor = $unitCostMinor * $quantity;
        $batchNumber = 'SUP-BATCH-'.strtoupper(Str::random(8));

        DB::transaction(function () use (
            $supplier,
            $validated,
            $batchNumber,
            $quantity,
            $unitCostMinor,
            $totalCostMinor,
            $accountResolver,
            $ledger
        ) {
            // 1. Create Supplier Batch
            $batch = SupplierBatch::create([
                'batch_number' => $batchNumber,
                'supplier_id' => $supplier->id,
                'product_variant_id' => (int) $validated['product_variant_id'],
                'quantity_supplied' => $quantity,
                'unit_cost_minor' => $unitCostMinor,
                'total_cost_minor' => $totalCostMinor,
                'status' => SupplierBatch::STATUS_RECEIVED,
                'received_at' => now(),
                'notes' => $validated['notes'] ?? null,
            ]);

            // 2. Increment Variant Stock
            /** @var ProductVariant $variant */
            $variant = ProductVariant::lockForUpdate()->findOrFail($validated['product_variant_id']);
            $variant->stock = ((int) ($variant->stock ?? 0)) + $quantity;
            $variant->save();

            // 3. Link variant wholesale price to supplier if not already set
            $wholesalePrice = WholesalePrice::where('product_variant_id', $variant->id)->first();
            if ($wholesalePrice instanceof WholesalePrice && ! $wholesalePrice->supplier_id) {
                $wholesalePrice->supplier_id = $supplier->id;
                $wholesalePrice->save();
            }

            // 4. Double-entry ledger: Dr Store Inventory / Cr Supplier Payable
            $inventoryAccount = $accountResolver->getStoreInventoryAccount();
            $supplierAccount = $accountResolver->getSupplierPayableAccount($supplier);

            $entryData = new EntryData(
                type: 'supplier_batch_received',
                idempotencyKey: "supplier_batch_{$batch->id}",
                description: "توريد وارد جديد #{$batchNumber} من المورد {$supplier->name} ({$quantity} قطعة)",
                lines: [
                    new LedgerLineData(account: $inventoryAccount, amountMinor: $totalCostMinor),
                    new LedgerLineData(account: $supplierAccount, amountMinor: -$totalCostMinor),
                ],
                refType: SupplierBatch::class,
                refId: (int) $batch->id,
                createdBy: null
            );

            $ledger->post($entryData);
        });

        return redirect()->route('store.suppliers.show', $supplier->id)
            ->with('success', "تم تسجيل الوارد بنجاح برقم إذن {$batchNumber} وإضافة الكمية للمخزون وترحيل المستحقات للدفتر.");
    }
}
