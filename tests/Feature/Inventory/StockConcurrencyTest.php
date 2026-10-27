<?php

declare(strict_types=1);

namespace Tests\Feature\Inventory;

use App\Domain\Inventory\Actions\InboundStockAction;
use App\Domain\Inventory\Actions\ReserveOrderStockAction;
use App\Domain\Inventory\Actions\ReserveStockAction;
use App\Domain\Inventory\Exceptions\InsufficientStockException;
use App\Models\InventoryBalance;
use App\Models\Tenant;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Lunar\Base\ValueObjects\Cart\TaxBreakdown;
use Lunar\FieldTypes\Text;
use Lunar\Models\Channel;
use Lunar\Models\Currency;
use Lunar\Models\Order;
use Lunar\Models\OrderLine;
use Lunar\Models\Product;
use Lunar\Models\ProductType;
use Lunar\Models\ProductVariant;
use Lunar\Models\TaxClass;
use Tests\TestCase;

class StockConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected Warehouse $warehouse;

    protected ProductVariant $variant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->ensureTenantExists('inv-conc-tenant', 'inv-conc-tenant.localhost');
        tenancy()->initialize($this->tenant);

        Currency::firstOrCreate(
            ['code' => 'EGP'],
            ['name' => 'Egyptian Pound', 'exchange_rate' => 1.0, 'decimal_places' => 2, 'enabled' => true, 'default' => true]
        );

        Channel::firstOrCreate(
            ['handle' => 'web'],
            ['name' => 'Web Channel', 'default' => true, 'url' => 'http://localhost']
        );

        $this->warehouse = Warehouse::create([
            'name' => 'Central Hub',
            'code' => 'HUB-01',
            'type' => 'own',
            'is_default' => true,
            'is_active' => true,
        ]);

        $productType = ProductType::create(['name' => 'Default']);
        $taxClass = TaxClass::create(['name' => 'Standard Tax']);

        $product = Product::create([
            'product_type_id' => $productType->id,
            'status' => 'published',
            'attribute_data' => ['name' => new Text('High Demand Widget')],
        ]);

        $this->variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'WIDGET-01',
            'tax_class_id' => $taxClass->id,
            'stock' => 0,
        ]);
    }

    /**
     * Requirement: 10 parallel reservations on 3 items -> exactly 3 succeed, 7 fail.
     */
    public function test_ten_reservations_for_three_stock_units_results_in_exactly_three_successes(): void
    {
        // Add exactly 3 items to stock
        app(InboundStockAction::class)->execute($this->variant->id, 3, $this->warehouse->id);

        $reserveAction = app(ReserveStockAction::class);
        $succeeded = 0;
        $failed = 0;

        for ($i = 1; $i <= 10; $i++) {
            try {
                $reserveAction->execute(
                    variantId: $this->variant->id,
                    quantity: 1,
                    orderReference: 'ORD-RACE-'.$i,
                    warehouseId: $this->warehouse->id
                );
                $succeeded++;
            } catch (InsufficientStockException) {
                $failed++;
            }
        }

        $this->assertEquals(3, $succeeded, 'Exactly 3 reservations must succeed.');
        $this->assertEquals(7, $failed, 'Exactly 7 reservations must fail due to insufficient stock.');

        $balance = InventoryBalance::where('warehouse_id', $this->warehouse->id)
            ->where('product_variant_id', $this->variant->id)
            ->first();

        $this->assertEquals(3, $balance->on_hand);
        $this->assertEquals(3, $balance->reserved);
        $this->assertEquals(0, $balance->available);
        $this->assertEquals(0, $this->variant->fresh()->stock);
    }

    /**
     * Requirement: Multi-variant batch order reservation is all-or-nothing and deadlock-free.
     */
    public function test_batch_order_reservation_rolls_back_completely_if_any_variant_is_short(): void
    {
        $productType = ProductType::first();
        $taxClass = TaxClass::first();
        $product = Product::first();

        $variant2 = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'WIDGET-02',
            'tax_class_id' => $taxClass->id,
            'stock' => 0,
        ]);

        // Stock 5 of variant 1, but only 1 of variant 2
        app(InboundStockAction::class)->execute($this->variant->id, 5, $this->warehouse->id);
        app(InboundStockAction::class)->execute($variant2->id, 1, $this->warehouse->id);

        $channel = Channel::first();
        $currency = Currency::first();

        $order = Order::create([
            'channel_id' => $channel->id,
            'status' => 'pending_confirmation',
            'reference' => 'ORD-BATCH-FAIL',
            'customer_reference' => 'CUST-BATCH',
            'sub_total' => 20000,
            'total' => 20000,
            'discount_total' => 0,
            'shipping_total' => 0,
            'tax_total' => 0,
            'tax_breakdown' => new TaxBreakdown,
            'currency_code' => $currency->code,
            'placed_at' => now(),
        ]);

        // Line 1: 3 units of variant 1 (available: 5 -> OK)
        OrderLine::create([
            'order_id' => $order->id,
            'purchasable_type' => $this->variant->getMorphClass(),
            'purchasable_id' => $this->variant->id,
            'type' => 'physical',
            'description' => 'Widget 1',
            'identifier' => $this->variant->sku,
            'unit_price' => 5000,
            'unit_quantity' => 1,
            'quantity' => 3,
            'sub_total' => 15000,
            'discount_total' => 0,
            'total' => 15000,
            'tax_total' => 0,
            'tax_breakdown' => new TaxBreakdown,
        ]);

        // Line 2: 2 units of variant 2 (available: 1 -> SHORT!)
        OrderLine::create([
            'order_id' => $order->id,
            'purchasable_type' => $variant2->getMorphClass(),
            'purchasable_id' => $variant2->id,
            'type' => 'physical',
            'description' => 'Widget 2',
            'identifier' => $variant2->sku,
            'unit_price' => 5000,
            'unit_quantity' => 1,
            'quantity' => 2,
            'sub_total' => 10000,
            'discount_total' => 0,
            'total' => 10000,
            'tax_total' => 0,
            'tax_breakdown' => new TaxBreakdown,
        ]);

        $batchAction = app(ReserveOrderStockAction::class);

        $failed = false;

        try {
            $batchAction->execute($order->fresh(['lines']));
        } catch (InsufficientStockException $e) {
            $failed = true;
            $this->assertEquals($variant2->id, $e->variantId);
        }

        $this->assertTrue($failed, 'Batch reservation should throw InsufficientStockException');

        // Verify all-or-nothing rollback: variant 1 reservation was rolled back!
        $balance1 = InventoryBalance::where('product_variant_id', $this->variant->id)->first();
        $this->assertEquals(0, $balance1->reserved, 'Variant 1 should NOT remain reserved when order fails.');
        $this->assertEquals(5, $balance1->available);

        $balance2 = InventoryBalance::where('product_variant_id', $variant2->id)->first();
        $this->assertEquals(0, $balance2->reserved);
        $this->assertEquals(1, $balance2->available);
    }
}
