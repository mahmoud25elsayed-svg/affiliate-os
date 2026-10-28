<?php

declare(strict_types=1);

namespace Tests\Feature\Tracking;

use App\Domain\Orders\Enums\OrderStatus;
use App\Domain\Orders\Services\OrderTransitionService;
use App\Domain\Tracking\Services\ConversionDataHasher;
use App\Domain\Tracking\Services\MetaConversionsApiService;
use App\Domain\Tracking\Services\TikTokEventsApiService;
use App\Domain\Tracking\Settings\AdTrackingSettings;
use App\Models\AdConversionEvent;
use App\Models\Affiliate;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy\Permissions\TenantPermissionMatrix;
use Database\Seeders\EgyptGovernoratesSeeder;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Lunar\Base\ValueObjects\Cart\TaxBreakdown;
use Lunar\FieldTypes\Text;
use Lunar\Models\Channel;
use Lunar\Models\Currency;
use Lunar\Models\Order;
use Lunar\Models\OrderAddress;
use Lunar\Models\Price;
use Lunar\Models\Product;
use Lunar\Models\ProductType;
use Lunar\Models\ProductVariant;
use Lunar\Models\TaxClass;
use Tests\TestCase;

class AdTrackingAndPixelsTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected string $domain;

    protected User $owner;

    protected Affiliate $affiliate;

    protected Channel $channel;

    protected ProductVariant $variant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PlanSeeder::class);
        $this->seed(EgyptGovernoratesSeeder::class);

        $proPlan = Plan::where('slug', 'pro')->firstOrFail();

        $this->tenant = $this->ensureTenantExists('pixel-store');
        $this->domain = $this->tenant->domains->first()?->domain ?? 'pixel-store.localhost';

        $this->owner = User::where('email', 'owner@pixel-store.test')->firstOrFail();
        TenantPermissionMatrix::seedForTenant($this->tenant->id);
        TenantPermissionMatrix::assignRole($this->owner, TenantPermissionMatrix::ROLE_OWNER, $this->tenant->id);

        Subscription::create([
            'tenant_id' => $this->tenant->id,
            'plan_id' => $proPlan->id,
            'status' => Subscription::STATUS_ACTIVE,
            'billing_cycle' => Subscription::CYCLE_MONTHLY,
            'current_period_starts_at' => now()->startOfMonth(),
            'current_period_ends_at' => now()->addMonth(),
        ]);

        $this->channel = Channel::first() ?? Channel::create([
            'name' => 'Default',
            'handle' => 'default',
            'default' => true,
        ]);

        $this->tenant->run(function () {
            $this->affiliate = Affiliate::create([
                'tenant_id' => $this->tenant->id,
                'name' => 'مسوق إعلانات',
                'phone' => '+201099887766',
                'email' => 'media_buyer@affiliate.test',
                'password' => bcrypt('secret123'),
                'code' => 'BUYER10',
                'status' => Affiliate::STATUS_ACTIVE,
                'meta_pixel_id' => 'AFF_META_999888',
                'tiktok_pixel_id' => 'AFF_TT_777666',
            ]);

            $productType = ProductType::first() ?? ProductType::create(['name' => 'Default']);
            $taxClass = TaxClass::first() ?? TaxClass::create(['name' => 'Default']);
            $currency = Currency::where('default', true)->first()
                ?? Currency::create(['code' => 'EGP', 'name' => 'Egyptian Pound', 'exchange_rate' => 1.0, 'decimal_places' => 2, 'enabled' => true, 'default' => true]);

            $product = Product::create([
                'tenant_id' => $this->tenant->id,
                'product_type_id' => $productType->id,
                'status' => 'published',
                'attribute_data' => [
                    'name' => new Text('سماعات عازلة للضوضاء'),
                ],
            ]);

            $this->variant = ProductVariant::create([
                'tenant_id' => $this->tenant->id,
                'product_id' => $product->id,
                'sku' => 'NOISE-CANCEL-01',
                'tax_class_id' => $taxClass->id,
                'stock' => 100,
            ]);

            Price::create([
                'tenant_id' => $this->tenant->id,
                'priceable_type' => $this->variant->getMorphClass(),
                'priceable_id' => $this->variant->id,
                'currency_id' => $currency->id,
                'price' => 75000, // 750.00 EGP
            ]);
        });
    }

    public function test_data_hasher_normalizes_and_hashes_per_meta_tiktok_specs(): void
    {
        // 1. Email hashing: trim + lowercase + sha256
        $email = '  Customer.Example@GMAIL.COM  ';
        $hashedEmail = ConversionDataHasher::hashEmail($email);
        $expectedEmail = hash('sha256', 'customer.example@gmail.com');
        $this->assertEquals($expectedEmail, $hashedEmail);

        // 2. Phone hashing: Egyptian standard normalization
        // "+20 10 1234 5678" -> "201012345678"
        $phone1 = '+20 10 1234 5678';
        $hashedPhone1 = ConversionDataHasher::hashPhone($phone1);
        $this->assertEquals(hash('sha256', '201012345678'), $hashedPhone1);

        // Local format "01012345678" -> "201012345678"
        $phone2 = '01012345678';
        $hashedPhone2 = ConversionDataHasher::hashPhone($phone2);
        $this->assertEquals(hash('sha256', '201012345678'), $hashedPhone2);

        // 3. City hashing: lowercase + whitespace stripped
        $city = ' Cairo - Nasr City ';
        $hashedCity = ConversionDataHasher::hashCity($city);
        $this->assertEquals(hash('sha256', 'cairo-nasrcity'), $hashedCity);
    }

    public function test_client_pixel_scripts_and_multi_pixel_rendering(): void
    {
        $this->tenant->run(function () {
            $settings = AdTrackingSettings::load();
            $settings->meta_pixel_enabled = true;
            $settings->meta_pixel_id = 'STORE_META_111222';
            $settings->tiktok_pixel_enabled = true;
            $settings->tiktok_pixel_id = 'STORE_TT_333444';
            $settings->save();
        });

        // 1. Storefront home without affiliate ref -> Only store pixels initialized
        $response1 = $this->get("http://{$this->domain}/");
        $response1->assertStatus(200);
        $response1->assertSee("fbq('init', 'STORE_META_111222')", false);
        $response1->assertSee("ttq.load('STORE_TT_333444')", false);
        $response1->assertDontSee('AFF_META_999888');

        // 2. Storefront with affiliate referral link (?ref=BUYER10) -> BOTH store and affiliate pixels initialized!
        $response2 = $this->get("http://{$this->domain}/?ref=BUYER10");
        $response2->assertStatus(200);
        // Store pixel
        $response2->assertSee("fbq('init', 'STORE_META_111222')", false);
        $response2->assertSee("ttq.load('STORE_TT_333444')", false);
        // Affiliate pixel
        $response2->assertSee("fbq('init', 'AFF_META_999888')", false);
        $response2->assertSee("ttq.load('AFF_TT_777666')", false);
        $response2->assertSee("fbq('track', 'PageView')", false);
    }

    public function test_meta_conversions_api_payload_and_http_fake(): void
    {
        Http::fake([
            'https://graph.facebook.com/v21.0/*' => Http::response(['events_received' => 1, 'messages' => []], 200),
        ]);

        $service = app(MetaConversionsApiService::class);

        $log = $service->sendEvent(
            tenantId: $this->tenant->id,
            pixelId: 'META_PIXEL_123',
            accessToken: 'EAATestToken...',
            eventName: 'Purchase',
            eventId: 'evt_purch_test_001',
            userData: [
                'email' => 'tarek@example.com',
                'phone' => '+201012345678',
                'first_name' => 'طارق',
                'city' => 'الإسكندرية',
            ],
            customData: [
                'currency' => 'EGP',
                'value' => 750.00,
                'order_id' => 'ORD-TEST-99',
            ],
            testEventCode: 'TEST1234'
        );

        $this->assertEquals(AdConversionEvent::STATUS_SENT, $log->status);
        $this->assertEquals(200, $log->response_code);

        // Verify sent payload to Meta Graph API
        Http::assertSent(function ($request) {
            $body = $request->data();
            $event = $body['data'][0];

            return str_contains($request->url(), 'META_PIXEL_123')
                && $event['event_name'] === 'Purchase'
                && $event['event_id'] === 'evt_purch_test_001'
                && $event['custom_data']['currency'] === 'EGP'
                && $event['custom_data']['value'] === 750.00
                && $event['user_data']['em'][0] === hash('sha256', 'tarek@example.com')
                && $event['user_data']['ph'][0] === hash('sha256', '201012345678')
                && $body['test_event_code'] === 'TEST1234';
        });

        $this->assertDatabaseHas('ad_conversion_events', [
            'tenant_id' => $this->tenant->id,
            'platform' => AdConversionEvent::PLATFORM_META,
            'event_name' => 'Purchase',
            'event_id' => 'evt_purch_test_001',
            'status' => AdConversionEvent::STATUS_SENT,
        ]);
    }

    public function test_tiktok_events_api_payload_and_http_fake(): void
    {
        Http::fake([
            'https://business-api.tiktok.com/open_api/v1.3/event/track/' => Http::response(['code' => 0, 'message' => 'OK'], 200),
        ]);

        $service = app(TikTokEventsApiService::class);

        $log = $service->sendEvent(
            tenantId: $this->tenant->id,
            pixelId: 'TT_PIXEL_999',
            accessToken: 'act_token_sample',
            eventName: 'CompletePayment',
            eventId: 'evt_tt_test_002',
            userData: [
                'email' => 'nada@example.com',
                'phone' => '01099887766',
            ],
            customData: [
                'currency' => 'EGP',
                'value' => 1200.00,
            ],
            testEventCode: 'TT_TEST_99'
        );

        $this->assertEquals(AdConversionEvent::STATUS_SENT, $log->status);
        $this->assertEquals(200, $log->response_code);

        Http::assertSent(function ($request) {
            $body = $request->data();
            $event = $body['data'][0];

            return $request->header('Access-Token')[0] === 'act_token_sample'
                && $body['event_source'] === 'web'
                && $body['event_source_id'] === 'TT_PIXEL_999'
                && $event['event'] === 'CompletePayment'
                && $event['event_id'] === 'evt_tt_test_002'
                && $event['user']['email'] === hash('sha256', 'nada@example.com')
                && $event['user']['phone'] === hash('sha256', '201099887766')
                && $body['test_event_code'] === 'TT_TEST_99';
        });

        $this->assertDatabaseHas('ad_conversion_events', [
            'tenant_id' => $this->tenant->id,
            'platform' => AdConversionEvent::PLATFORM_TIKTOK,
            'event_name' => 'CompletePayment',
            'event_id' => 'evt_tt_test_002',
            'status' => AdConversionEvent::STATUS_SENT,
        ]);
    }

    public function test_event_id_deduplication_consistency_between_browser_and_server(): void
    {
        Http::fake();

        $this->tenant->run(function () {
            $settings = AdTrackingSettings::load();
            $settings->meta_pixel_enabled = true;
            $settings->meta_pixel_id = 'STORE_META_555';
            $settings->meta_conversions_api_token = 'meta_secret_token';
            $settings->save();
        });

        // Create an order
        $order = null;
        $this->tenant->run(function () use (&$order) {
            OrderTransitionService::$isTransitioning = true;

            try {
                $order = Order::create([
                    'tenant_id' => $this->tenant->id,
                    'channel_id' => $this->channel->id,
                    'reference' => 'ORD-DEDUP-777',
                    'customer_reference' => 'ORD-DEDUP-777',
                    'status' => OrderStatus::PENDING_CONFIRMATION->value,
                    'sub_total' => 75000,
                    'total' => 80000, // 800.00 EGP
                    'discount_total' => 0,
                    'shipping_total' => 5000,
                    'tax_total' => 0,
                    'tax_breakdown' => new TaxBreakdown,
                    'currency_code' => 'EGP',
                    'payment_method' => 'cod',
                    'placed_at' => now(),
                ]);

                OrderAddress::create([
                    'order_id' => $order->id,
                    'type' => 'shipping',
                    'first_name' => 'يوسف',
                    'last_name' => 'الشريف',
                    'phone' => '+201011223344',
                    'city' => 'المهندسين',
                ]);
            } finally {
                OrderTransitionService::$isTransitioning = false;
            }
        });

        // Customer visits checkout success page
        $res = $this->get("http://{$this->domain}/checkout/success/ORD-DEDUP-777");
        $res->assertStatus(200);

        // Fetch the logged server CAPI event
        /** @var AdConversionEvent $capiEvent */
        $capiEvent = AdConversionEvent::where('order_id', $order->id)
            ->where('event_name', AdConversionEvent::EVENT_PURCHASE)
            ->firstOrFail();

        $this->assertNotEmpty($capiEvent->event_id);

        // Crucial Acceptance Criterion:
        // The EXACT same event_id is embedded in the browser's trackAdEvent call for deduplication!
        $res->assertSee($capiEvent->event_id);
        $res->assertSee("window.trackAdEvent('Purchase'", false);
    }

    public function test_custom_delivered_event_triggered_on_actual_delivery(): void
    {
        Http::fake();

        $this->tenant->run(function () {
            $settings = AdTrackingSettings::load();
            $settings->meta_pixel_enabled = true;
            $settings->meta_pixel_id = 'STORE_META_DELIV';
            $settings->meta_conversions_api_token = 'meta_secret_token';
            $settings->track_delivered_event = true;
            $settings->save();
        });

        $order = null;
        $this->tenant->run(function () use (&$order) {
            OrderTransitionService::$isTransitioning = true;

            try {
                $order = Order::create([
                    'tenant_id' => $this->tenant->id,
                    'channel_id' => $this->channel->id,
                    'reference' => 'ORD-DELIV-888',
                    'customer_reference' => 'ORD-DELIV-888',
                    'status' => OrderStatus::OUT_FOR_DELIVERY->value,
                    'sub_total' => 75000,
                    'total' => 75000,
                    'discount_total' => 0,
                    'shipping_total' => 0,
                    'tax_total' => 0,
                    'tax_breakdown' => new TaxBreakdown,
                    'currency_code' => 'EGP',
                    'payment_method' => 'cod',
                ]);

                OrderAddress::create([
                    'order_id' => $order->id,
                    'type' => 'shipping',
                    'first_name' => 'أحمد',
                    'phone' => '+201055566778',
                    'city' => 'الشيخ زايد',
                ]);
            } finally {
                OrderTransitionService::$isTransitioning = false;
            }
        });

        // Simulate delivery transition via OrderTransitionService
        $this->tenant->run(function () use ($order) {
            $transitionService = app(OrderTransitionService::class);
            $transitionService->transition(
                order: $order,
                to: OrderStatus::DELIVERED,
                note: 'Order successfully delivered to customer by courier'
            );
        });

        $order->refresh();
        $this->assertEquals(OrderStatus::DELIVERED->value, $order->status);

        // Verify that Delivered conversion event was recorded in diagnostic table
        $this->assertDatabaseHas('ad_conversion_events', [
            'tenant_id' => $this->tenant->id,
            'order_id' => $order->id,
            'event_name' => AdConversionEvent::EVENT_DELIVERED,
            'platform' => AdConversionEvent::PLATFORM_META,
        ]);
    }

    public function test_diagnostic_panel_displays_events_and_handles_test_ping(): void
    {
        Http::fake([
            'https://graph.facebook.com/*' => Http::response(['success' => true], 200),
            'https://business-api.tiktok.com/*' => Http::response(['code' => 0], 200),
        ]);

        $this->actingAs($this->owner);

        // 1. Visit diagnostic panel
        $res = $this->get("http://{$this->domain}/store/pixels");
        $res->assertStatus(200);
        $res->assertSee('التتبع الإعلاني');
        $res->assertSee('لوحة تشخيص ومراقبة الأحداث المرسلة');

        // 2. Update tracking settings
        $updateRes = $this->post("http://{$this->domain}/store/pixels", [
            'meta_pixel_enabled' => 1,
            'meta_pixel_id' => '1234567890',
            'meta_conversions_api_token' => 'EAATokenSample...',
            'tiktok_pixel_enabled' => 1,
            'tiktok_pixel_id' => 'TIKTOK_PIXEL_1',
            'tiktok_events_api_token' => 'tt_token_sample...',
            'track_delivered_event' => 1,
        ]);

        $updateRes->assertRedirect(route('store.pixels.index'));
        $updateRes->assertSessionHas('success');

        // 3. Test ping Meta CAPI
        $testMeta = $this->post("http://{$this->domain}/store/pixels/test", [
            'platform' => 'meta',
        ]);
        $testMeta->assertRedirect(route('store.pixels.index'));

        // 4. Test ping TikTok API
        $testTikTok = $this->post("http://{$this->domain}/store/pixels/test", [
            'platform' => 'tiktok',
        ]);
        $testTikTok->assertRedirect(route('store.pixels.index'));

        // Verify TestEvent was logged
        $this->assertDatabaseHas('ad_conversion_events', [
            'tenant_id' => $this->tenant->id,
            'event_name' => 'TestEvent',
            'status' => AdConversionEvent::STATUS_SENT,
        ]);
    }

    public function test_affiliate_can_update_their_own_pixels(): void
    {
        $this->actingAs($this->affiliate, 'affiliate');

        // 1. Visit affiliate pixel settings
        $res = $this->get("http://{$this->domain}/partners/pixels");
        $res->assertStatus(200);
        $res->assertSee('بكسلات التتبع الإعلاني');

        // 2. Update pixel IDs
        $updateRes = $this->post("http://{$this->domain}/partners/pixels", [
            'meta_pixel_id' => 'AFF_NEW_META_123',
            'tiktok_pixel_id' => 'AFF_NEW_TT_456',
        ]);

        $updateRes->assertRedirect(route('affiliate.pixels.show'));
        $updateRes->assertSessionHas('success');

        $this->affiliate->refresh();
        $this->assertEquals('AFF_NEW_META_123', $this->affiliate->meta_pixel_id);
        $this->assertEquals('AFF_NEW_TT_456', $this->affiliate->tiktok_pixel_id);
    }
}
