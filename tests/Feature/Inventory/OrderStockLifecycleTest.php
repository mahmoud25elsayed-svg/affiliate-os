<?php

declare(strict_types=1);

namespace Tests\Feature\Inventory;

use App\Domain\Inventory\Actions\InboundStockAction;
use App\Domain\Orders\DTOs\OrderActor;
use App\Domain\Orders\Enums\OrderStatus;
use App\Domain\Orders\Services\OrderTransitionService;
use App\Models\InventoryBalance;
use App\Models\Tenant;
use App\Models\Warehouse;
use App\Support\Enums\ReasonCode;
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

class OrderStockLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected Warehouse $warehouse;

    protected ProductVariant $variant1;

    protected ProductVariant $variant2;

    protected OrderTransitionService $transitionService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->ensureTenantExists('inv-order-tenant', 'inv-order-tenant.localhost');
        tenancy()->initialize($this->tenant);

        $this->transitionService = app(OrderTransitionService::class);

        Currency::firstOrCreate(
            ['code' => 'EGP'],
            ['name' => 'Egyptian Pound', 'exchange_rate' => 1.0, 'decimal_places' => 2, 'enabled' => true, 'default' => true]
        );

        Channel::firstOrCreate(
            ['handle' => 'web'],
            ['name' => 'Web Channel', 'default' => true, 'url' => 'http://localhost']
        );

        $this->warehouse = Warehouse::create([
            'name' => 'Main Depot',
            'code' => 'MAIN-01',
            'type' => 'own',
            'is_default' => true,
            'is_active' => true,
        ]);

        $productType = ProductType::create(['name' => 'General']);
        $taxClass = TaxClass::create(['name' => 'Default Tax']);

        $product = Product::create([
            'product_type_id' => $productType->id,
            'status' => 'published',
            'attribute_data' => ['name' => new Text('T-Shirt')],
        ]);

        $this->variant1 = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'TSHIRT-RED-M',
            'tax_class_id' => $taxClass->id,
            'stock' => 0,
        ]);

        $this->variant2 = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'TSHIRT-BLUE-L',
            'tax_class_id' => $taxClass->id,
            'stock' => 0,
        ]);
    }

    protected function createOrder(int $variant1Qty = 2, int $variant2Qty = 1, OrderStatus $initialStatus = OrderStatus::PENDING_CONFIRMATION): Order
    {
        $channel = Channel::first();
        $currency = Currency::first();

        $order = Order::create([
            'channel_id' => $channel->id,
            'status' => $initialStatus->value,
            'reference' => 'ORD-'.uniqid(),
            'customer_reference' => 'CUST-1',
            'sub_total' => 30000,
            'total' => 30000,
            'discount_total' => 0,
            'shipping_total' => 0,
            'tax_total' => 0,
            'tax_breakdown' => new TaxBreakdown,
            'currency_code' => $currency->code,
            'compare_currency_code' => $currency->code,
            'exchange_rate' => 1.0,
            'placed_at' => now(),
        ]);

        if ($variant1Qty > 0) {
            OrderLine::create([
                'order_id' => $order->id,
                'purchasable_type' => $this->variant1->getMorphClass(),
                'purchasable_id' => $this->variant1->id,
                'type' => 'physical',
                'description' => 'Red T-Shirt',
                'option' => 'Red / M',
                'identifier' => $this->variant1->sku,
                'unit_price' => 10000,
                'unit_quantity' => 1,
                'quantity' => $variant1Qty,
                'sub_total' => 10000 * $variant1Qty,
                'discount_total' => 0,
                'total' => 10000 * $variant1Qty,
                'tax_total' => 0,
                'tax_breakdown' => new TaxBreakdown,
            ]);
        }

        if ($variant2Qty > 0) {
            OrderLine::create([
                'order_id' => $order->id,
                'purchasable_type' => $this->variant2->getMorphClass(),
                'purchasable_id' => $this->variant2->id,
                'type' => 'physical',
                'description' => 'Blue T-Shirt',
                'option' => 'Blue / L',
                'identifier' => $this->variant2->sku,
                'unit_price' => 10000,
                'unit_quantity' => 1,
                'quantity' => $variant2Qty,
                'sub_total' => 10000 * $variant2Qty,
                'discount_total' => 0,
                'total' => 10000 * $variant2Qty,
                'tax_total' => 0,
                'tax_breakdown' => new TaxBreakdown,
            ]);
        }

        return $order->fresh(['lines']);
    }

    public function test_order_confirmation_reserves_stock_and_transitions_to_reserved(): void
    {
        // Stock both items in warehouse
        app(InboundStockAction::class)->execute($this->variant1->id, 10, $this->warehouse->id);
        app(InboundStockAction::class)->execute($this->variant2->id, 5, $this->warehouse->id);

        $order = $this->createOrder(variant1Qty: 2, variant2Qty: 1);

        // Transition PENDING_CONFIRMATION -> CONFIRMED
        $this->transitionService->transition(
            order: $order,
            to: OrderStatus::CONFIRMED,
            reason: null,
            actor: OrderActor::staff(1, 'Confirm Agent', 'call_center')
        );

        // OrderConfirmedStockListener handles reservation and auto-transitions to RESERVED
        $this->assertEquals(OrderStatus::RESERVED->value, $order->fresh()->status);

        $balance1 = InventoryBalance::where('product_variant_id', $this->variant1->id)->first();
        $balance2 = InventoryBalance::where('product_variant_id', $this->variant2->id)->first();

        $this->assertEquals(2, $balance1->reserved);
        $this->assertEquals(8, $balance1->available);
        $this->assertEquals(1, $balance2->reserved);
        $this->assertEquals(4, $balance2->available);

        $this->assertEquals(8, $this->variant1->fresh()->stock);
        $this->assertEquals(4, $this->variant2->fresh()->stock);
    }

    public function test_order_confirmation_transitions_to_out_of_stock_when_stock_is_insufficient(): void
    {
        // Only stock variant 1, variant 2 has 0 stock
        app(InboundStockAction::class)->execute($this->variant1->id, 10, $this->warehouse->id);

        $order = $this->createOrder(variant1Qty: 2, variant2Qty: 5); // variant 2 needs 5, has 0

        $this->transitionService->transition(
            order: $order,
            to: OrderStatus::CONFIRMED,
            reason: null,
            actor: OrderActor::staff(1, 'Agent', 'call_center')
        );

        // Should transition to OUT_OF_STOCK
        $this->assertEquals(OrderStatus::OUT_OF_STOCK->value, $order->fresh()->status);

        // All-or-nothing rollback: variant 1 should NOT remain reserved
        $balance1 = InventoryBalance::where('product_variant_id', $this->variant1->id)->first();
        $this->assertEquals(0, $balance1->reserved);
        $this->assertEquals(10, $balance1->available);
    }

    public function test_order_cancellation_releases_reserved_stock(): void
    {
        app(InboundStockAction::class)->execute($this->variant1->id, 10, $this->warehouse->id);
        app(InboundStockAction::class)->execute($this->variant2->id, 5, $this->warehouse->id);

        $order = $this->createOrder(variant1Qty: 3, variant2Qty: 2);

        $this->transitionService->transition($order, OrderStatus::CONFIRMED, null, OrderActor::staff(1, 'Agent', 'call_center'));
        $this->assertEquals(OrderStatus::RESERVED->value, $order->fresh()->status);

        $balance1 = InventoryBalance::where('product_variant_id', $this->variant1->id)->first();
        $this->assertEquals(3, $balance1->reserved);

        // Cancel order
        $this->transitionService->transition(
            order: $order,
            to: OrderStatus::CANCELLED_BY_CUSTOMER,
            reason: ReasonCode::CUSTOMER_CHANGED_MIND,
            actor: OrderActor::customer('CUST-1')
        );

        // Stock released
        $this->assertEquals(0, $balance1->fresh()->reserved);
        $this->assertEquals(10, $balance1->fresh()->available);
        $this->assertEquals(10, $this->variant1->fresh()->stock);
    }

    public function test_order_shipped_permanently_deducts_stock(): void
    {
        app(InboundStockAction::class)->execute($this->variant1->id, 10, $this->warehouse->id);
        $order = $this->createOrder(variant1Qty: 4, variant2Qty: 0);

        $order = $this->transitionService->transition($order, OrderStatus::CONFIRMED, null, OrderActor::staff(1, 'Agent', 'call_center'));
        $order = $order->fresh();
        $order = $this->transitionService->transition($order, OrderStatus::PACKED, null, OrderActor::staff(2, 'Packer', 'orders'));

        // Transition PACKED -> SHIPPED
        $this->transitionService->transition($order, OrderStatus::SHIPPED, null, OrderActor::staff(3, 'Dispatcher', 'orders'));

        $balance1 = InventoryBalance::where('product_variant_id', $this->variant1->id)->first();
        $this->assertEquals(6, $balance1->on_hand);
        $this->assertEquals(0, $balance1->reserved);
        $this->assertEquals(6, $balance1->available);
        $this->assertEquals(6, $this->variant1->fresh()->stock);
    }

    public function test_order_returned_restocks_sound_goods(): void
    {
        app(InboundStockAction::class)->execute($this->variant1->id, 10, $this->warehouse->id);
        $order = $this->createOrder(variant1Qty: 3, variant2Qty: 0);

        $order = $this->transitionService->transition($order, OrderStatus::CONFIRMED, null, OrderActor::staff(1, 'Agent', 'call_center'));
        $order = $order->fresh();
        $order = $this->transitionService->transition($order, OrderStatus::PACKED, null, OrderActor::staff(2, 'Packer', 'orders'));
        $order = $this->transitionService->transition($order, OrderStatus::SHIPPED, null, OrderActor::staff(3, 'Dispatcher', 'orders'));
        $order = $this->transitionService->transition($order, OrderStatus::RETURNING, ReasonCode::CUSTOMER_UNREACHABLE, OrderActor::staff(3, 'Driver', 'orders'));

        // Return order (sound item)
        $this->transitionService->transition(
            order: $order,
            to: OrderStatus::RETURNED,
            reason: null,
            actor: OrderActor::staff(4, 'Returns Inspector', 'orders')
        );

        $balance1 = InventoryBalance::where('product_variant_id', $this->variant1->id)->first();
        $this->assertEquals(10, $balance1->on_hand);
        $this->assertEquals(10, $balance1->available);
        $this->assertEquals(10, $this->variant1->fresh()->stock);
    }
}
