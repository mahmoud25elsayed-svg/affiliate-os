<?php

declare(strict_types=1);

namespace Tests\Feature\Orders;

use App\Domain\Orders\Enums\OrderStatus;
use App\Domain\Orders\Events\OrderConfirmed;
use App\Domain\Orders\Services\OrderTransitionService;
use App\Filament\Store\Resources\OrderResource\Pages\ListOrders;
use App\Filament\Store\Resources\OrderResource\Pages\ViewOrder;
use App\Models\Customer;
use App\Models\OrderEvent;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy\Permissions\TenantPermissionMatrix;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;
use Lunar\Base\ValueObjects\Cart\TaxBreakdown;
use Lunar\FieldTypes\Text;
use Lunar\Models\Channel;
use Lunar\Models\Currency;
use Lunar\Models\Order;
use Lunar\Models\OrderAddress;
use Lunar\Models\OrderLine;
use Lunar\Models\Product;
use Lunar\Models\ProductType;
use Lunar\Models\ProductVariant;
use Lunar\Models\TaxClass;
use Tests\TestCase;

class FilamentOrderResourceTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected User $staffUser;

    protected Order $order;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->ensureTenantExists('filstore', 'fil-store.localhost');
        tenancy()->initialize($this->tenant);
        TenantPermissionMatrix::seedForTenant($this->tenant->id);

        $this->staffUser = User::create([
            'name' => 'Store Manager',
            'email' => 'manager@filstore.test',
            'password' => 'secret123',
            'tenant_id' => $this->tenant->id,
            'is_platform_admin' => false,
        ]);
        TenantPermissionMatrix::assignRole($this->staffUser, TenantPermissionMatrix::ROLE_MANAGER, $this->tenant->id);

        Currency::firstOrCreate(['code' => 'EGP'], [
            'name' => 'Egyptian Pound',
            'exchange_rate' => 1.0,
            'decimal_places' => 2,
            'enabled' => true,
            'default' => true,
        ]);

        $channel = Channel::first() ?? Channel::create([
            'name' => 'Web',
            'handle' => 'web',
            'default' => true,
            'url' => 'http://fil-store.localhost',
        ]);

        $customer = Customer::create([
            'first_name' => 'كريم',
            'last_name' => 'عادل',
            'phone' => '01211112222',
            'phone_e164' => '+201211112222',
            'phone_hash' => hash('sha256', '+201211112222'),
            'email' => 'karim@example.com',
        ]);

        $productType = ProductType::create(['name' => 'Default']);
        $taxClass = TaxClass::create(['name' => 'Default']);
        $product = Product::create([
            'product_type_id' => $productType->id,
            'status' => 'published',
            'attribute_data' => [
                'name' => new Text('حذاء رياضي'),
            ],
        ]);
        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'SKU-SHOES-1',
            'tax_class_id' => $taxClass->id,
        ]);

        OrderTransitionService::$isTransitioning = true;

        try {
            $this->order = Order::create([
                'storefront_customer_id' => $customer->id,
                'channel_id' => $channel->id,
                'status' => OrderStatus::PENDING_CONFIRMATION->value,
                'reference' => 'ORD-FIL-888999',
                'customer_reference' => 'ORD-FIL-888999',
                'sub_total' => 60000,
                'total' => 65000,
                'discount_total' => 0,
                'shipping_total' => 5000,
                'tax_total' => 0,
                'tax_breakdown' => new TaxBreakdown,
                'currency_code' => 'EGP',
                'customer_phone_e164' => '+201211112222',
                'payment_method' => 'cod',
                'placed_at' => now(),
            ]);
        } finally {
            OrderTransitionService::$isTransitioning = false;
        }

        OrderLine::create([
            'order_id' => $this->order->id,
            'purchasable_type' => ProductVariant::class,
            'purchasable_id' => $variant->id,
            'type' => 'physical',
            'description' => 'حذاء رياضي مريح',
            'identifier' => 'SKU-SHOES-1',
            'unit_price' => 60000,
            'unit_quantity' => 1,
            'quantity' => 1,
            'sub_total' => 60000,
            'discount_total' => 0,
            'tax_breakdown' => new TaxBreakdown,
            'tax_total' => 0,
            'total' => 60000,
        ]);

        OrderAddress::create([
            'order_id' => $this->order->id,
            'first_name' => 'كريم',
            'last_name' => 'عادل',
            'line_one' => 'شارع مصطفى النحاس - مدينة نصر',
            'city' => 'القاهرة',
            'contact_phone' => '01211112222',
            'type' => 'shipping',
            'shipping_option' => 'standard',
        ]);

        OrderEvent::create([
            'tenant_id' => $this->tenant->id,
            'order_id' => $this->order->id,
            'from_status' => null,
            'to_status' => OrderStatus::SUBMITTED->value,
            'reason_code' => null,
            'actor_type' => 'customer',
            'actor_name' => 'كريم عادل',
            'note' => 'طلب مسجل من المتجر',
            'created_at' => now()->subMinutes(15),
        ]);

        OrderEvent::create([
            'tenant_id' => $this->tenant->id,
            'order_id' => $this->order->id,
            'from_status' => OrderStatus::SUBMITTED->value,
            'to_status' => OrderStatus::PENDING_CONFIRMATION->value,
            'reason_code' => null,
            'actor_type' => 'system',
            'actor_name' => 'النظام الآلي',
            'note' => 'تم الفحص بنجاح',
            'created_at' => now()->subMinutes(14),
        ]);
    }

    public function test_orders_table_lists_orders_and_renders_columns(): void
    {
        $this->actingAs($this->staffUser);
        Filament::setCurrentPanel(Filament::getPanel('store'));
        Filament::setTenant($this->tenant);

        Livewire::test(ListOrders::class)
            ->assertSuccessful()
            ->assertCanSeeTableRecords([$this->order]);
    }

    public function test_view_order_page_renders_details_and_timeline(): void
    {
        $this->actingAs($this->staffUser);
        Filament::setCurrentPanel(Filament::getPanel('store'));
        Filament::setTenant($this->tenant);

        Livewire::test(ViewOrder::class, ['record' => $this->order->getRouteKey()])
            ->assertSuccessful()
            ->assertSee('ORD-FIL-888999')
            ->assertSee('كريم عادل')
            ->assertSee('حذاء رياضي مريح')
            ->assertSee('650.00 ج.م')
            ->assertSee('سجل حالات الطلب (Timeline)')
            ->assertSee('طلب مسجل من المتجر');
    }

    public function test_executing_transition_action_from_view_order_page(): void
    {
        Event::fake([OrderConfirmed::class]);

        $this->actingAs($this->staffUser);
        Filament::setCurrentPanel(Filament::getPanel('store'));
        Filament::setTenant($this->tenant);

        Livewire::test(ViewOrder::class, ['record' => $this->order->getRouteKey()])
            ->assertSuccessful()
            ->callAction('transition_to_confirmed', [
                'note' => 'تم تأكيد الطلب مع العميل هاتفياً',
            ])
            ->assertHasNoActionErrors();

        $this->assertEquals(OrderStatus::CONFIRMED->value, $this->order->fresh()->status);

        $latestEvent = OrderEvent::where('order_id', $this->order->id)->latest('id')->first();
        $this->assertNotNull($latestEvent);
        $this->assertEquals(OrderStatus::CONFIRMED->value, $latestEvent->to_status);
        $this->assertEquals('تم تأكيد الطلب مع العميل هاتفياً', $latestEvent->note);

        Event::assertDispatched(OrderConfirmed::class, 1);
    }
}
