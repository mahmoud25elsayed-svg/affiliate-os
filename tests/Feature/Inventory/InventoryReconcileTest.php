<?php

declare(strict_types=1);

namespace Tests\Feature\Inventory;

use App\Domain\Inventory\Actions\InboundStockAction;
use App\Models\InventoryBalance;
use App\Models\Tenant;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Lunar\FieldTypes\Text;
use Lunar\Models\Currency;
use Lunar\Models\Product;
use Lunar\Models\ProductType;
use Lunar\Models\ProductVariant;
use Lunar\Models\TaxClass;
use Tests\TestCase;

class InventoryReconcileTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected Warehouse $warehouse;

    protected ProductVariant $variant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->ensureTenantExists('reconcile-tenant', 'reconcile-tenant.localhost');
        tenancy()->initialize($this->tenant);

        Currency::firstOrCreate(
            ['code' => 'EGP'],
            ['name' => 'Egyptian Pound', 'exchange_rate' => 1.0, 'decimal_places' => 2, 'enabled' => true, 'default' => true]
        );

        $this->warehouse = Warehouse::create([
            'name' => 'Audit Warehouse',
            'code' => 'AUD-01',
            'type' => 'own',
            'is_default' => true,
            'is_active' => true,
        ]);

        $productType = ProductType::create(['name' => 'Standard']);
        $taxClass = TaxClass::create(['name' => 'Default']);

        $product = Product::create([
            'product_type_id' => $productType->id,
            'status' => 'published',
            'attribute_data' => ['name' => new Text('Audit Item')],
        ]);

        $this->variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'SKU-AUDIT-01',
            'tax_class_id' => $taxClass->id,
            'stock' => 0,
        ]);
    }

    public function test_reconcile_command_succeeds_when_balances_match_movements(): void
    {
        app(InboundStockAction::class)->execute($this->variant->id, 15, $this->warehouse->id);

        $this->artisan('inventory:reconcile')
            ->expectsOutputToContain('100% consistent with movement audit trails')
            ->assertExitCode(0);
    }

    public function test_reconcile_command_detects_and_fixes_discrepancies(): void
    {
        app(InboundStockAction::class)->execute($this->variant->id, 20, $this->warehouse->id);

        // Artificially alter the balance directly in database without recording a movement
        DB::table('inventory_balances')
            ->where('warehouse_id', $this->warehouse->id)
            ->where('product_variant_id', $this->variant->id)
            ->update(['on_hand' => 14]); // Discrepancy: movements say 20, balance says 14

        // Without --fix, command must fail and report discrepancy
        $this->artisan('inventory:reconcile')
            ->expectsOutputToContain('Found 1 discrepancies')
            ->assertExitCode(1);

        // With --fix, command repairs the discrepancy and exits with 0
        $this->artisan('inventory:reconcile', ['--fix' => true])
            ->expectsOutputToContain('All discrepancies have been automatically corrected')
            ->assertExitCode(0);

        $balance = InventoryBalance::where('warehouse_id', $this->warehouse->id)
            ->where('product_variant_id', $this->variant->id)
            ->first();

        $this->assertEquals(20, $balance->on_hand, 'Balance on_hand must be restored to 20 based on movements.');
    }
}
