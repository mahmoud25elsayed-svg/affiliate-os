<?php

declare(strict_types=1);

namespace Tests\Feature\Orders;

use App\Domain\Orders\Enums\OrderStatus;
use App\Domain\Orders\Services\OrderTransitionService;
use App\Models\Customer;
use App\Models\OrderEvent;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy\Permissions\TenantPermissionMatrix;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Lunar\Base\ValueObjects\Cart\TaxBreakdown;
use Lunar\FieldTypes\Text;
use Lunar\Models\Channel;
use Lunar\Models\Order;
use Lunar\Models\OrderAddress;
use Lunar\Models\OrderLine;
use Lunar\Models\Product;
use Lunar\Models\ProductType;
use Lunar\Models\ProductVariant;
use Lunar\Models\TaxClass;
use Tests\TestCase;

class OrderDocumentsTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected Order $order;

    protected Customer $customer;

    protected User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->ensureTenantExists('docstore', 'doc-store.localhost');
        tenancy()->initialize($this->tenant);
        TenantPermissionMatrix::seedForTenant($this->tenant->id);

        $this->staff = User::firstOrCreate(
            ['email' => 'owner@docstore.test'],
            [
                'name' => 'Store Owner',
                'password' => bcrypt('password123'),
                'tenant_id' => $this->tenant->id,
            ]
        );
        TenantPermissionMatrix::assignRole($this->staff, TenantPermissionMatrix::ROLE_OWNER, $this->tenant->id);

        $channel = Channel::first() ?? Channel::create([
            'name' => 'Web',
            'handle' => 'web',
            'default' => true,
            'url' => 'http://doc-store.localhost',
        ]);

        $this->customer = Customer::create([
            'first_name' => 'محمود',
            'last_name' => 'السيد',
            'phone' => '01012345678',
            'phone_e164' => '+201012345678',
            'phone_hash' => hash('sha256', '+201012345678'),
            'email' => 'mahmoud@example.com',
        ]);

        $productType = ProductType::create(['name' => 'Default']);
        $taxClass = TaxClass::create(['name' => 'Default']);
        $product = Product::create([
            'product_type_id' => $productType->id,
            'status' => 'published',
            'attribute_data' => [
                'name' => new Text('قميص قطني أبيض كلاسيك'),
            ],
        ]);
        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'SKU-DOC-1',
            'tax_class_id' => $taxClass->id,
        ]);

        OrderTransitionService::$isTransitioning = true;

        try {
            $this->order = Order::create([
                'storefront_customer_id' => $this->customer->id,
                'channel_id' => $channel->id,
                'status' => OrderStatus::PENDING_CONFIRMATION->value,
                'reference' => 'ORD-DOC-123456',
                'customer_reference' => 'ORD-DOC-123456',
                'sub_total' => 25000,
                'total' => 30000,
                'discount_total' => 0,
                'shipping_total' => 5000,
                'tax_total' => 0,
                'tax_breakdown' => new TaxBreakdown,
                'currency_code' => 'EGP',
                'customer_phone_e164' => '+201012345678',
                'payment_method' => 'cod',
                'cod_fee_minor' => 0,
                'notes' => 'يرجى الاتصال قبل الوصول بنصف ساعة',
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
            'description' => 'قميص قطني أبيض كلاسيك',
            'identifier' => 'SKU-DOC-1',
            'unit_price' => 25000,
            'unit_quantity' => 1,
            'quantity' => 1,
            'sub_total' => 25000,
            'discount_total' => 0,
            'tax_breakdown' => new TaxBreakdown,
            'tax_total' => 0,
            'total' => 25000,
        ]);

        OrderAddress::create([
            'order_id' => $this->order->id,
            'first_name' => 'محمود',
            'last_name' => 'السيد',
            'line_one' => 'شارع التحرير - الدقي',
            'city' => 'الجيزة',
            'contact_phone' => '01012345678',
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
            'actor_name' => 'محمود السيد',
            'note' => 'تم إنشاء الطلب',
            'created_at' => now()->subMinutes(10),
        ]);

        OrderEvent::create([
            'tenant_id' => $this->tenant->id,
            'order_id' => $this->order->id,
            'from_status' => OrderStatus::SUBMITTED->value,
            'to_status' => OrderStatus::PENDING_CONFIRMATION->value,
            'reason_code' => null,
            'actor_type' => 'system',
            'actor_name' => 'النظام الآلي',
            'note' => 'اجتاز فحص الجودة بنجاح',
            'created_at' => now()->subMinutes(9),
        ]);
    }

    public function test_invoice_renders_arabic_content_and_financials(): void
    {
        $domain = 'doc-store.localhost';

        // Unauthenticated access must be rejected with 403
        $guestResponse = $this->get("http://{$domain}/orders/{$this->order->reference}/invoice");
        $guestResponse->assertForbidden();

        // Authenticated staff user can view
        $this->actingAs($this->staff);
        $response = $this->get("http://{$domain}/orders/{$this->order->reference}/invoice");

        $response->assertOk();
        $response->assertSee('فاتورة مبيعات');
        $response->assertSee('ORD-DOC-123456');
        $response->assertSee('محمود السيد');
        $response->assertSee('قميص قطني أبيض كلاسيك');
        $response->assertSee('300.00 ج.م');
        $response->assertSee('الدفع عند الاستلام');
    }

    public function test_invoice_pdf_download(): void
    {
        $domain = 'doc-store.localhost';
        $this->actingAs($this->staff);
        $response = $this->get("http://{$domain}/orders/{$this->order->reference}/invoice?pdf=1");

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_shipping_waybill_renders_courier_details_and_cod(): void
    {
        $domain = 'doc-store.localhost';

        // Unauthenticated access must be rejected with 403
        $guestResponse = $this->get("http://{$domain}/orders/{$this->order->reference}/waybill");
        $guestResponse->assertForbidden();

        // Authenticated staff user can view
        $this->actingAs($this->staff);
        $response = $this->get("http://{$domain}/orders/{$this->order->reference}/waybill");

        $response->assertOk();
        $response->assertSee('بوليصة شحن وتوصيل داخلية');
        $response->assertSee('ORD-DOC-123456');
        $response->assertSee('المبلغ المطلوب تحصيله نقداً عند الاستلام');
        $response->assertSee('300.00 ج.م');
        $response->assertSee('شارع التحرير - الدقي');
        $response->assertSee('الجيزة');
    }

    public function test_shipping_waybill_pdf_download(): void
    {
        $domain = 'doc-store.localhost';
        $this->actingAs($this->staff);
        $response = $this->get("http://{$domain}/orders/{$this->order->reference}/waybill?pdf=1");

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_customer_portal_order_details_displays_timeline(): void
    {
        $domain = 'doc-store.localhost';
        $this->actingAs($this->customer, 'customer');

        $response = $this->get("http://{$domain}/account/orders/{$this->order->reference}");

        $response->assertOk();
        $response->assertSee('متابعة مسار الطلب');
        $response->assertSee('سجل التحديثات:');
        $response->assertSee('تم استلام طلبك بنجاح');
        $response->assertSee('قيد مراجعة وتأكيد البيانات');
    }
}
