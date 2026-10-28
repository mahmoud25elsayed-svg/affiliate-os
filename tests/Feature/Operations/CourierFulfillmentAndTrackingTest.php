<?php

declare(strict_types=1);

namespace Tests\Feature\Operations;

use App\Domain\Fulfillment\Contracts\CourierDriverInterface;
use App\Domain\Fulfillment\Drivers\BostaCourierDriver;
use App\Domain\Fulfillment\Drivers\MockCourierDriver;
use App\Domain\Fulfillment\DTOs\ShipmentCreationData;
use App\Domain\Fulfillment\Enums\ShipmentStatus;
use App\Domain\Fulfillment\Services\CourierManager;
use App\Domain\Fulfillment\Services\CourierSelectionService;
use App\Domain\Fulfillment\Services\ShipmentDispatchService;
use App\Domain\Fulfillment\Services\ShipmentTrackingSyncService;
use App\Domain\Orders\Enums\OrderStatus;
use App\Domain\Orders\Services\OrderTransitionService;
use App\Models\Affiliate;
use App\Models\CourierAccount;
use App\Models\CourierRule;
use App\Models\OrderEvent;
use App\Models\Plan;
use App\Models\Shipment;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WholesalePrice;
use App\Support\Billing\Exceptions\FeatureNotAvailableException;
use App\Support\Location\City;
use App\Support\Location\Governorate;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Lunar\Base\ValueObjects\Cart\TaxBreakdown;
use Lunar\FieldTypes\Text;
use Lunar\Models\Channel;
use Lunar\Models\Country;
use Lunar\Models\Currency;
use Lunar\Models\Language;
use Lunar\Models\Order;
use Lunar\Models\OrderAddress;
use Lunar\Models\OrderLine;
use Lunar\Models\Product;
use Lunar\Models\ProductType;
use Lunar\Models\ProductVariant;
use Lunar\Models\TaxClass;
use Tests\TestCase;

class CourierFulfillmentAndTrackingTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected User $staffUser;

    protected Channel $channel;

    protected Currency $currency;

    protected Country $country;

    protected Governorate $cairo;

    protected Governorate $alex;

    protected ProductVariant $variant;

    protected Affiliate $affiliate;

    protected CourierAccount $mockAccount;

    protected CourierAccount $bostaAccount;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PlanSeeder::class);

        $this->tenant = Tenant::create([
            'id' => 'ful-test-store',
            'name' => 'Fulfillment Test Store',
            'status' => 'active',
        ]);
        $this->tenant->domains()->create(['domain' => 'ful-test-store.localhost']);
        tenancy()->initialize($this->tenant);

        /** @var Plan $proPlan */
        $proPlan = Plan::where('slug', 'pro')->firstOrFail();
        $this->tenant->subscriptions()->create([
            'plan_id' => $proPlan->id,
            'status' => Subscription::STATUS_ACTIVE,
            'billing_cycle' => Subscription::CYCLE_MONTHLY,
            'current_period_starts_at' => now(),
            'current_period_ends_at' => now()->addMonth(),
        ]);

        Language::firstOrCreate(['code' => 'ar'], [
            'name' => 'Arabic',
            'default' => true,
        ]);

        $this->country = Country::firstOrCreate(
            ['iso3' => 'EGY'],
            [
                'name' => 'Egypt',
                'iso2' => 'EG',
                'phonecode' => '+20',
                'currency' => 'EGP',
                'emoji' => '🇪🇬',
                'emoji_u' => 'U+1F1EA U+1F1EC',
            ]
        );

        $this->currency = Currency::create([
            'code' => 'EGP',
            'name' => 'Egyptian Pound',
            'exchange_rate' => 1.0,
            'decimal_places' => 2,
            'enabled' => true,
            'default' => true,
        ]);

        $this->channel = Channel::create([
            'name' => 'Web',
            'handle' => 'web',
            'default' => true,
            'url' => 'http://ful-test-store.localhost',
        ]);

        $this->cairo = Governorate::create([
            'code' => 'CAI',
            'name_ar' => 'القاهرة',
            'name_en' => 'Cairo',
            'is_active' => true,
        ]);

        $this->alex = Governorate::create([
            'code' => 'ALX',
            'name_ar' => 'الإسكندرية',
            'name_en' => 'Alexandria',
            'is_active' => true,
        ]);

        City::create([
            'governorate_id' => $this->cairo->id,
            'name_ar' => 'مدينة نصر',
            'name_en' => 'Nasr City',
            'shipping_cost_minor' => 4500,
            'is_active' => true,
        ]);

        Warehouse::firstOrCreate([
            'tenant_id' => $this->tenant->id,
            'code' => 'MAIN',
        ], [
            'name' => 'المخزن الرئيسي',
            'type' => 'physical',
            'is_default' => true,
            'is_active' => true,
        ]);

        $this->staffUser = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'مسؤول الشحن',
            'email' => 'fulfillment_agent@test.com',
            'password' => bcrypt('password'),
        ]);

        $this->affiliate = Affiliate::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'مسوق الشحن',
            'code' => 'AFF-FUL-01',
            'phone' => '+201011223399',
            'email' => 'affiliate_ful@test.com',
            'status' => 'active',
        ]);

        // Create product & variant
        $taxClass = TaxClass::create(['name' => 'Default Tax']);
        $productType = ProductType::create(['name' => 'Standard Product']);
        $product = Product::create([
            'product_type_id' => $productType->id,
            'status' => 'published',
            'attribute_data' => ['name' => new Text('ساعة ذكية فاخرة')],
        ]);

        $this->variant = ProductVariant::create([
            'product_id' => $product->id,
            'tax_class_id' => $taxClass->id,
            'sku' => 'WATCH-01',
            'unit_quantity' => 1,
        ]);

        WholesalePrice::create([
            'tenant_id' => $this->tenant->id,
            'product_variant_id' => $this->variant->id,
            'wholesale_price_minor' => 20000,
            'min_price_minor' => 25000,
            'max_price_minor' => 45000,
            'status' => WholesalePrice::STATUS_ACTIVE,
        ]);

        // Create Courier Accounts
        $this->mockAccount = CourierAccount::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Mock Logistics',
            'driver' => 'mock',
            'is_active' => true,
            'is_default' => true,
            'webhook_secret' => 'mock-secret-123',
            'settings' => [
                'pickup_address' => 'Cairo Hub',
            ],
        ]);

        $this->bostaAccount = CourierAccount::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Bosta Express',
            'driver' => 'bosta',
            'is_active' => true,
            'is_default' => false,
            'api_key' => 'bosta-test-key',
            'webhook_secret' => 'bosta-secret-456',
            'settings' => [
                'pickup_address' => 'Nasr City Hub',
            ],
        ]);
    }

    protected function createOrder(OrderStatus $status = OrderStatus::CONFIRMED, ?int $governorateId = null): Order
    {
        $gov = $governorateId ? Governorate::find($governorateId) : $this->cairo;
        $govName = $gov ? $gov->name_ar : 'القاهرة';

        OrderTransitionService::$isTransitioning = true;

        try {
            /** @var Order $order */
            $order = Order::create([
                'tenant_id' => $this->tenant->id,
                'channel_id' => $this->channel->id,
                'currency_code' => $this->currency->code,
                'status' => $status->value,
                'reference' => 'FUL-'.bin2hex(random_bytes(4)),
                'customer_reference' => 'CUST-'.bin2hex(random_bytes(3)),
                'sub_total' => 35000,
                'total' => 39500,
                'shipping_total' => 4500,
                'discount_total' => 0,
                'tax_total' => 0,
                'tax_breakdown' => new TaxBreakdown,
                'placed_at' => now(),
                'price_snapshot' => json_encode([
                    'quantity' => 1,
                    'selling_price_minor' => 35000,
                    'subtotal_minor' => 35000,
                    'shipping_cost_minor' => 4500,
                    'customer_total_minor' => 39500,
                    'governorate_id' => $gov ? $gov->id : $this->cairo->id,
                    'affiliate_id' => $this->affiliate->id,
                ]),
                'notes' => 'يرجى المعاينة قبل الاستلام',
            ]);

            OrderAddress::create([
                'order_id' => $order->id,
                'type' => 'shipping',
                'country_id' => $this->country->id,
                'first_name' => 'محمود',
                'last_name' => 'السيد',
                'line_one' => 'شارع عباس العقاد - عمارة 14',
                'city' => $govName,
                'contact_phone' => '01012345678',
            ]);

            OrderLine::create([
                'order_id' => $order->id,
                'purchasable_type' => ProductVariant::class,
                'purchasable_id' => $this->variant->id,
                'type' => 'physical',
                'identifier' => 'WATCH-01',
                'unit_quantity' => 1,
                'quantity' => 1,
                'unit_price' => 35000,
                'sub_total' => 35000,
                'discount_total' => 0,
                'tax_breakdown' => new TaxBreakdown,
                'tax_total' => 0,
                'total' => 35000,
                'description' => 'ساعة ذكية فاخرة',
            ]);

            return $order;
        } finally {
            OrderTransitionService::$isTransitioning = false;
        }
    }

    public function test_courier_driver_factory_resolves_mock_and_bosta_drivers(): void
    {
        /** @var CourierManager $manager */
        $manager = app(CourierManager::class);

        $mockDriver = $manager->driver($this->mockAccount);
        $this->assertInstanceOf(CourierDriverInterface::class, $mockDriver);
        $this->assertInstanceOf(MockCourierDriver::class, $mockDriver);

        $bostaDriver = $manager->driver($this->bostaAccount);
        $this->assertInstanceOf(CourierDriverInterface::class, $bostaDriver);
        $this->assertInstanceOf(BostaCourierDriver::class, $bostaDriver);
    }

    public function test_courier_selection_service_matches_governorate_rule_and_priority(): void
    {
        /** @var CourierSelectionService $selectionService */
        $selectionService = app(CourierSelectionService::class);

        // Without rules, falls back to default courier account
        $orderCairo = $this->createOrder(governorateId: $this->cairo->id);
        $selectedDefault = $selectionService->selectCourier($orderCairo);
        $this->assertNotNull($selectedDefault);
        $this->assertSame($this->mockAccount->id, $selectedDefault->id);

        // Create a specific rule for Cairo pointing to Bosta with priority 10
        CourierRule::create([
            'tenant_id' => $this->tenant->id,
            'courier_account_id' => $this->bostaAccount->id,
            'governorate_id' => $this->cairo->id,
            'priority' => 10,
            'cod_limit_minor' => 100000, // up to 1000 EGP
            'is_active' => true,
        ]);

        $selectedWithRule = $selectionService->selectCourier($orderCairo);
        $this->assertNotNull($selectedWithRule);
        $this->assertSame($this->bostaAccount->id, $selectedWithRule->id);

        // When order total exceeds cod_limit_minor, it should skip this rule
        CourierRule::query()->where('courier_account_id', $this->bostaAccount->id)->update([
            'cod_limit_minor' => 20000, // 200 EGP limit, while order is 395 EGP
        ]);

        $selectedAfterLimit = $selectionService->selectCourier($orderCairo);
        $this->assertNotNull($selectedAfterLimit);
        $this->assertSame($this->mockAccount->id, $selectedAfterLimit->id);
    }

    public function test_shipment_dispatch_service_dispatches_order_through_valid_state_machine(): void
    {
        /** @var ShipmentDispatchService $dispatchService */
        $dispatchService = app(ShipmentDispatchService::class);

        $order = $this->createOrder(OrderStatus::CONFIRMED);
        $this->assertSame(OrderStatus::CONFIRMED->value, (string) $order->status);

        $shipment = $dispatchService->dispatchOrder($order, $this->mockAccount, $this->staffUser);

        // Assert shipment record is created
        $this->assertInstanceOf(Shipment::class, $shipment);
        $this->assertDatabaseHas('shipments', [
            'id' => $shipment->id,
            'order_id' => $order->id,
            'courier_account_id' => $this->mockAccount->id,
            'status' => ShipmentStatus::CREATED->value,
            'recipient_name' => 'محمود السيد',
            'recipient_phone' => '01012345678',
            'cod_amount_minor' => 39500,
        ]);

        $this->assertStringStartsWith('MCK-', $shipment->tracking_number);
        $this->assertNotNull($shipment->waybill_url);

        // Assert order transitioned: CONFIRMED -> RESERVED -> PACKED -> SHIPPED
        $order->refresh();
        $this->assertSame(OrderStatus::SHIPPED->value, (string) $order->status);

        // Assert OrderEvent audit history
        $shippedEvent = OrderEvent::query()
            ->where('order_id', $order->id)
            ->where('to_status', OrderStatus::SHIPPED->value)
            ->first();

        $this->assertNotNull($shippedEvent);
        $this->assertSame($shipment->tracking_number, $shippedEvent->meta['tracking_number'] ?? null);
        $this->assertStringContainsString($this->mockAccount->name, (string) $shippedEvent->note);
    }

    public function test_shipment_tracking_sync_cascades_out_for_delivery_and_delivered(): void
    {
        /** @var ShipmentDispatchService $dispatchService */
        $dispatchService = app(ShipmentDispatchService::class);
        /** @var ShipmentTrackingSyncService $syncService */
        $syncService = app(ShipmentTrackingSyncService::class);

        $order = $this->createOrder(OrderStatus::CONFIRMED);
        $shipment = $dispatchService->dispatchOrder($order, $this->mockAccount);

        // 1. Shipment out for delivery
        $syncService->syncStatus($shipment, ShipmentStatus::OUT_FOR_DELIVERY, 'مندوب الشحن في الطريق للعميل');
        $shipment->refresh();
        $order->refresh();

        $this->assertSame(ShipmentStatus::OUT_FOR_DELIVERY, $shipment->status);
        $this->assertSame(OrderStatus::OUT_FOR_DELIVERY->value, (string) $order->status);

        // 2. Shipment delivered
        $syncService->syncStatus($shipment, ShipmentStatus::DELIVERED, 'تم التسليم بنجاح');
        $shipment->refresh();
        $order->refresh();

        $this->assertSame(ShipmentStatus::DELIVERED, $shipment->status);
        $this->assertNotNull($shipment->delivered_at);
        $this->assertSame(OrderStatus::DELIVERED->value, (string) $order->status);

        $deliveredEvent = OrderEvent::query()
            ->where('order_id', $order->id)
            ->where('to_status', OrderStatus::DELIVERED->value)
            ->first();

        $this->assertNotNull($deliveredEvent);
        $this->assertStringContainsString('395.00 ج.م', (string) $deliveredEvent->note);
    }

    public function test_shipment_tracking_sync_cascades_failed_attempt_and_returns(): void
    {
        /** @var ShipmentDispatchService $dispatchService */
        $dispatchService = app(ShipmentDispatchService::class);
        /** @var ShipmentTrackingSyncService $syncService */
        $syncService = app(ShipmentTrackingSyncService::class);

        $order = $this->createOrder(OrderStatus::CONFIRMED);
        $shipment = $dispatchService->dispatchOrder($order, $this->mockAccount);

        // Out for delivery
        $syncService->syncStatus($shipment, ShipmentStatus::OUT_FOR_DELIVERY);

        // Delivery attempt failed
        $syncService->syncStatus($shipment, ShipmentStatus::FAILED_ATTEMPT, 'العميل لا يجيب على الهاتف');
        $shipment->refresh();
        $order->refresh();

        $this->assertSame(ShipmentStatus::FAILED_ATTEMPT, $shipment->status);
        $this->assertSame(OrderStatus::DELIVERY_FAILED->value, (string) $order->status);

        // Returning to merchant
        $syncService->syncStatus($shipment, ShipmentStatus::RETURNING, 'العميل رفض استلام المنتج عند الباب');
        $shipment->refresh();
        $order->refresh();

        $this->assertSame(ShipmentStatus::RETURNING, $shipment->status);
        $this->assertSame(OrderStatus::RETURNING->value, (string) $order->status);

        // Returned and inspected at warehouse
        $syncService->syncStatus($shipment, ShipmentStatus::RETURNED, 'تم استلام الشحنة المرتجعة');
        $shipment->refresh();
        $order->refresh();

        $this->assertSame(ShipmentStatus::RETURNED, $shipment->status);
        $this->assertSame(OrderStatus::RETURNED->value, (string) $order->status);
    }

    public function test_courier_webhook_updates_status_with_valid_signature(): void
    {
        /** @var ShipmentDispatchService $dispatchService */
        $dispatchService = app(ShipmentDispatchService::class);

        $order = $this->createOrder(OrderStatus::CONFIRMED);
        $shipment = $dispatchService->dispatchOrder($order, $this->mockAccount);

        $response = $this->postJson('https://ful-test-store.localhost/webhooks/courier/mock', [
            'trackingNumber' => $shipment->tracking_number,
            'state' => 'OUT_FOR_DELIVERY',
            'note' => 'تم استلام الشحنة من الفرع وجاري التوصيل',
        ], [
            'X-Webhook-Signature' => 'mock-secret-123',
        ]);

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'tracking_number' => $shipment->tracking_number,
            'shipment_status' => 'out_for_delivery',
            'order_status' => 'out_for_delivery',
        ]);

        $shipment->refresh();
        $order->refresh();
        $this->assertSame(ShipmentStatus::OUT_FOR_DELIVERY, $shipment->status);
        $this->assertSame(OrderStatus::OUT_FOR_DELIVERY->value, (string) $order->status);
    }

    public function test_courier_webhook_rejects_invalid_signature(): void
    {
        /** @var ShipmentDispatchService $dispatchService */
        $dispatchService = app(ShipmentDispatchService::class);

        $order = $this->createOrder(OrderStatus::CONFIRMED);
        $shipment = $dispatchService->dispatchOrder($order, $this->mockAccount);

        $response = $this->postJson('https://ful-test-store.localhost/webhooks/courier/mock', [
            'trackingNumber' => $shipment->tracking_number,
            'state' => 'DELIVERED',
        ], [
            'X-Webhook-Signature' => 'wrong-signature-attack',
        ]);

        $response->assertStatus(401);

        $shipment->refresh();
        $this->assertNotSame(ShipmentStatus::DELIVERED, $shipment->status);
    }

    public function test_merchant_can_view_shipments_list_and_details_in_store_admin(): void
    {
        /** @var ShipmentDispatchService $dispatchService */
        $dispatchService = app(ShipmentDispatchService::class);

        $order = $this->createOrder(OrderStatus::CONFIRMED);
        $shipment = $dispatchService->dispatchOrder($order, $this->mockAccount);

        // Index page
        $response = $this->actingAs($this->staffUser)
            ->get('https://ful-test-store.localhost/store/shipments');

        $response->assertOk();
        $response->assertViewIs('store.shipments.index');
        $response->assertSee($shipment->tracking_number);
        $response->assertSee('Mock Logistics');

        // Show page
        $showResponse = $this->actingAs($this->staffUser)
            ->get("https://ful-test-store.localhost/store/shipments/{$shipment->id}");

        $showResponse->assertOk();
        $showResponse->assertViewIs('store.shipments.show');
        $showResponse->assertSee($shipment->tracking_number);
        $showResponse->assertSee('محمود السيد');
    }

    public function test_courier_feature_flag_is_enforced_for_unauthorized_plans(): void
    {
        // Downgrade tenant to starter plan which has courier = false
        /** @var Plan $starterPlan */
        $starterPlan = Plan::where('slug', 'starter')->firstOrFail();
        $this->tenant->subscriptions()->delete();
        $this->tenant->subscriptions()->create([
            'plan_id' => $starterPlan->id,
            'status' => Subscription::STATUS_ACTIVE,
            'billing_cycle' => Subscription::CYCLE_MONTHLY,
            'current_period_starts_at' => now(),
            'current_period_ends_at' => now()->addMonth(),
        ]);

        $this->expectException(FeatureNotAvailableException::class);

        $this->withoutExceptionHandling();
        $this->actingAs($this->staffUser)
            ->get('https://ful-test-store.localhost/store/shipments');
    }

    public function test_bosta_driver_creates_shipment_via_http_api(): void
    {
        Http::fake([
            'https://app.bosta.co/api/v2/deliveries' => Http::response([
                'success' => true,
                'data' => [
                    '_id' => 'bosta_del_9988',
                    'trackingNumber' => 'BST-99887766',
                ],
            ], 200),
        ]);

        $this->bostaAccount->update(['api_key' => 'bosta-secret-live-token']);

        /** @var CourierManager $manager */
        $manager = app(CourierManager::class);
        $driver = $manager->driver($this->bostaAccount);

        $order = $this->createOrder(OrderStatus::CONFIRMED);
        $creationData = new ShipmentCreationData(
            order: $order,
            recipientName: 'محمود أحمد',
            recipientPhone: '01099887766',
            deliveryGovernorate: 'القاهرة',
            deliveryCity: 'مدينة نصر',
            deliveryAddress: 'شارع النزهة',
            codAmountMinor: 39500,
            declaredValueMinor: 39500,
            itemsCount: 1,
            description: "طلب #{$order->reference}"
        );

        $result = $driver->createShipment($creationData);

        $this->assertTrue($result->success);
        $this->assertSame('BST-99887766', $result->trackingNumber);
        $this->assertStringContainsString('bosta_del_9988', (string) $result->waybillUrl);
    }

    public function test_bosta_driver_tracks_shipment_via_http_api(): void
    {
        Http::fake([
            'https://app.bosta.co/api/v2/deliveries/BST-99887766/tracking' => Http::response([
                'state' => [
                    'value' => 'Delivered',
                ],
                'hub' => 'Cairo Hub',
            ], 200),
        ]);

        $this->bostaAccount->update(['api_key' => 'bosta-secret-live-token']);

        /** @var CourierManager $manager */
        $manager = app(CourierManager::class);
        $driver = $manager->driver($this->bostaAccount);

        $tracking = $driver->trackShipment('BST-99887766');

        $this->assertSame('BST-99887766', $tracking->trackingNumber);
        $this->assertSame(ShipmentStatus::DELIVERED, $tracking->status);
    }
}
