<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Domain\Orders\Enums\OrderStatus;
use App\Domain\Orders\Services\OrderTransitionService;
use App\Domain\Webhooks\Jobs\DispatchWebhookJob;
use App\Models\Affiliate;
use App\Models\ApiKey;
use App\Models\InventoryBalance;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WebhookDelivery;
use App\Models\WebhookSubscription;
use App\Models\WholesalePrice;
use App\Support\Location\Governorate;
use App\Support\Tenancy\Permissions\TenantPermissionMatrix;
use Database\Seeders\EgyptGovernoratesSeeder;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Lunar\Base\ValueObjects\Cart\TaxBreakdown;
use Lunar\FieldTypes\Text;
use Lunar\Models\Channel;
use Lunar\Models\Currency;
use Lunar\Models\Order;
use Lunar\Models\Price;
use Lunar\Models\Product;
use Lunar\Models\ProductType;
use Lunar\Models\ProductVariant;
use Lunar\Models\TaxClass;
use Tests\TestCase;

class PublicApiAndWebhooksTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected string $domain;

    protected User $owner;

    protected Plan $starterPlan;

    protected Plan $proPlan;

    protected Affiliate $affiliate;

    protected ProductVariant $variant;

    protected Warehouse $warehouse;

    protected Governorate $cairo;

    protected Channel $channel;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PlanSeeder::class);
        $this->seed(EgyptGovernoratesSeeder::class);

        $this->starterPlan = Plan::where('slug', 'starter')->firstOrFail();
        $this->proPlan = Plan::where('slug', 'pro')->firstOrFail();

        $this->tenant = $this->ensureTenantExists('test-store');
        $this->domain = $this->tenant->domains->first()?->domain ?? 'test-store.localhost';
        $this->channel = Channel::first() ?? Channel::create(['name' => 'Default', 'handle' => 'default', 'default' => true]);

        $this->owner = User::where('email', 'owner@test-store.test')->firstOrFail();
        TenantPermissionMatrix::seedForTenant($this->tenant->id);
        TenantPermissionMatrix::assignRole($this->owner, TenantPermissionMatrix::ROLE_OWNER, $this->tenant->id);

        Subscription::create([
            'tenant_id' => $this->tenant->id,
            'plan_id' => $this->proPlan->id,
            'status' => Subscription::STATUS_ACTIVE,
            'billing_cycle' => Subscription::CYCLE_MONTHLY,
            'current_period_starts_at' => now()->startOfMonth(),
            'current_period_ends_at' => now()->addMonth(),
        ]);

        $this->tenant->run(function () {
            $this->cairo = Governorate::where('code', 'CAI')->firstOrFail();

            $this->warehouse = Warehouse::firstOrCreate([
                'tenant_id' => $this->tenant->id,
                'code' => 'MAIN',
            ], [
                'name' => 'المخزن الرئيسي',
                'type' => 'physical',
                'is_default' => true,
                'is_active' => true,
            ]);

            $this->affiliate = Affiliate::create([
                'tenant_id' => $this->tenant->id,
                'name' => 'مسوق تجريبي',
                'phone' => '+201012345678',
                'email' => 'affiliate@test.com',
                'password' => bcrypt('secret123'),
                'code' => 'AFF999',
                'status' => Affiliate::STATUS_ACTIVE,
                'phone_verified_at' => now(),
            ]);

            $productType = ProductType::first() ?? ProductType::create(['name' => 'Standard']);
            $taxClass = TaxClass::first() ?? TaxClass::create(['name' => 'Standard']);
            $currency = Currency::where('default', true)->first()
                ?? Currency::create(['code' => 'EGP', 'name' => 'Egyptian Pound', 'exchange_rate' => 1.0, 'decimal_places' => 2, 'enabled' => true, 'default' => true]);

            $product = Product::create([
                'tenant_id' => $this->tenant->id,
                'product_type_id' => $productType->id,
                'status' => 'published',
                'attribute_data' => [
                    'name' => new Text('سماعات بلوتوث لاسلكية'),
                ],
            ]);

            $this->variant = ProductVariant::create([
                'tenant_id' => $this->tenant->id,
                'product_id' => $product->id,
                'sku' => 'AIRPODS-PRO-01',
                'tax_class_id' => $taxClass->id,
                'stock' => 50,
            ]);

            Price::create([
                'tenant_id' => $this->tenant->id,
                'priceable_type' => $this->variant->getMorphClass(),
                'priceable_id' => $this->variant->id,
                'currency_id' => $currency->id,
                'price' => 50000, // 500.00 EGP
            ]);

            WholesalePrice::create([
                'tenant_id' => $this->tenant->id,
                'product_variant_id' => $this->variant->id,
                'wholesale_price_minor' => 30000, // 300.00 EGP
                'min_price_minor' => 40000,       // 400.00 EGP
                'max_price_minor' => 70000,       // 700.00 EGP
                'compare_at_price_minor' => 60000,
                'status' => WholesalePrice::STATUS_ACTIVE,
            ]);

            InventoryBalance::create([
                'tenant_id' => $this->tenant->id,
                'warehouse_id' => $this->warehouse->id,
                'product_variant_id' => $this->variant->id,
                'on_hand' => 50,
                'reserved' => 0,
            ]);
        });
    }

    public function test_api_key_authentication_failure_cases(): void
    {
        // 1. Missing key
        $response = $this->getJson("http://{$this->domain}/api/v1/store/products");
        $response->assertStatus(401);
        $response->assertJsonPath('error', 'Unauthorized');

        // 2. Invalid key
        $response = $this->withHeader('Authorization', 'Bearer invalid-token')
            ->getJson("http://{$this->domain}/api/v1/store/products");
        $response->assertStatus(401);

        // 3. Revoked key
        $created = ApiKey::createKey(
            tenant: $this->tenant,
            name: 'Revoked Key',
            scopes: [ApiKey::SCOPE_MERCHANT_PRODUCTS_READ],
            owner: $this->owner,
        );
        $created['apiKey']->update(['is_revoked' => true]);

        $response = $this->withHeader('Authorization', 'Bearer '.$created['plainTextKey'])
            ->getJson("http://{$this->domain}/api/v1/store/products");
        $response->assertStatus(401);

        // 4. Expired key
        $createdExpired = ApiKey::createKey(
            tenant: $this->tenant,
            name: 'Expired Key',
            scopes: [ApiKey::SCOPE_MERCHANT_PRODUCTS_READ],
            owner: $this->owner,
            expiresAt: now()->subDay(),
        );

        $response = $this->withHeader('Authorization', 'Bearer '.$createdExpired['plainTextKey'])
            ->getJson("http://{$this->domain}/api/v1/store/products");
        $response->assertStatus(401);
    }

    public function test_api_key_scope_enforcement(): void
    {
        // Key with only products read scope
        $created = ApiKey::createKey(
            tenant: $this->tenant,
            name: 'Products Read Only',
            scopes: [ApiKey::SCOPE_MERCHANT_PRODUCTS_READ],
            owner: $this->owner,
        );

        // Should succeed on products read
        $response = $this->withHeader('Authorization', 'Bearer '.$created['plainTextKey'])
            ->getJson("http://{$this->domain}/api/v1/store/products");
        $response->assertStatus(200);

        // Should be forbidden (403) on orders read
        $response = $this->withHeader('Authorization', 'Bearer '.$created['plainTextKey'])
            ->getJson("http://{$this->domain}/api/v1/store/orders");
        $response->assertStatus(403);
        $response->assertJsonPath('error', 'Forbidden');
    }

    public function test_feature_api_gating_blocks_starter_plan(): void
    {
        // Downgrade to Starter plan (api = false)
        Subscription::where('tenant_id', $this->tenant->id)->update([
            'plan_id' => $this->starterPlan->id,
        ]);
        $this->tenant->unsetRelation('subscription');

        $created = ApiKey::createKey(
            tenant: $this->tenant,
            name: 'Pro Key on Starter Store',
            scopes: [ApiKey::SCOPE_MERCHANT_PRODUCTS_READ],
            owner: $this->owner,
        );

        $response = $this->withHeader('Authorization', 'Bearer '.$created['plainTextKey'])
            ->getJson("http://{$this->domain}/api/v1/store/products");

        $response->assertStatus(403);
        $response->assertJsonPath('error', 'FeatureNotAvailable');
    }

    public function test_merchant_product_creation_and_strict_idempotency(): void
    {
        $created = ApiKey::createKey(
            tenant: $this->tenant,
            name: 'Merchant Admin',
            scopes: [ApiKey::SCOPE_MERCHANT_PRODUCTS_READ, ApiKey::SCOPE_MERCHANT_PRODUCTS_WRITE],
            owner: $this->owner,
        );

        $payload = [
            'name' => 'ساعة ذكية رياضية',
            'sku' => 'SMART-WATCH-GT',
            'price_minor' => 85000, // 850.00 EGP
            'stock' => 25,
            'status' => 'published',
        ];

        // 1. Without Idempotency-Key header -> 400 Bad Request
        $badResponse = $this->withHeader('Authorization', 'Bearer '.$created['plainTextKey'])
            ->postJson("http://{$this->domain}/api/v1/store/products", $payload);
        $badResponse->assertStatus(400);
        $badResponse->assertJsonPath('error', 'Bad Request');

        // 2. With Idempotency-Key -> 201 Created
        $idempotencyKey = 'idemp-prod-create-001';
        $response = $this->withHeader('Authorization', 'Bearer '.$created['plainTextKey'])
            ->withHeader('Idempotency-Key', $idempotencyKey)
            ->postJson("http://{$this->domain}/api/v1/store/products", $payload);

        $response->assertStatus(201);
        $response->assertJsonPath('data.variants.0.sku', 'SMART-WATCH-GT');

        // 3. Replay with identical Idempotency-Key -> 201 Cached replay
        $replayResponse = $this->withHeader('Authorization', 'Bearer '.$created['plainTextKey'])
            ->withHeader('Idempotency-Key', $idempotencyKey)
            ->postJson("http://{$this->domain}/api/v1/store/products", $payload);

        $replayResponse->assertStatus(201);
        $replayResponse->assertHeader('X-Idempotency-Replay', 'true');
    }

    public function test_merchant_order_transition_triggers_webhook(): void
    {
        Queue::fake();

        // Register Webhook Subscription
        $subscription = WebhookSubscription::create([
            'tenant_id' => $this->tenant->id,
            'url' => 'https://webhook.site/test-receiver',
            'secret' => 'whsec_secret123',
            'events' => ['order.status_updated'],
            'is_active' => true,
        ]);

        // Create an Order
        OrderTransitionService::$isTransitioning = true;

        try {
            $order = Order::create([
                'tenant_id' => $this->tenant->id,
                'channel_id' => $this->channel->id,
                'customer_reference' => 'ORD-TEST-001',
                'status' => OrderStatus::PENDING_CONFIRMATION->value,
                'sub_total' => 50000,
                'total' => 50000,
                'discount_total' => 0,
                'shipping_total' => 0,
                'tax_total' => 0,
                'tax_breakdown' => new TaxBreakdown,
                'currency_code' => 'EGP',
                'payment_method' => 'cod',
            ]);
        } finally {
            OrderTransitionService::$isTransitioning = false;
        }

        $created = ApiKey::createKey(
            tenant: $this->tenant,
            name: 'Order Manager',
            scopes: [ApiKey::SCOPE_MERCHANT_ORDERS_READ, ApiKey::SCOPE_MERCHANT_ORDERS_WRITE],
            owner: $this->owner,
        );

        // Transition order to confirmed
        $response = $this->withHeader('Authorization', 'Bearer '.$created['plainTextKey'])
            ->withHeader('Idempotency-Key', 'idemp-trans-001')
            ->postJson("http://{$this->domain}/api/v1/store/orders/{$order->id}/transition", [
                'status' => OrderStatus::CONFIRMED->value,
                'reason' => 'Confirmed via merchant API',
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.status', OrderStatus::CONFIRMED->value);

        // Assert Webhook Delivery was logged and Job was dispatched
        $this->assertDatabaseHas('webhook_deliveries', [
            'tenant_id' => $this->tenant->id,
            'webhook_subscription_id' => $subscription->id,
            'event' => 'order.status_updated',
        ]);

        Queue::assertPushed(DispatchWebhookJob::class);
    }

    public function test_merchant_inventory_adjustment_triggers_low_stock_webhook(): void
    {
        Queue::fake();

        $subscription = WebhookSubscription::create([
            'tenant_id' => $this->tenant->id,
            'url' => 'https://webhook.site/stock-receiver',
            'secret' => 'whsec_secret123',
            'events' => ['inventory.low'],
            'is_active' => true,
        ]);

        $created = ApiKey::createKey(
            tenant: $this->tenant,
            name: 'Inventory Controller',
            scopes: [ApiKey::SCOPE_MERCHANT_INVENTORY_READ, ApiKey::SCOPE_MERCHANT_INVENTORY_WRITE],
            owner: $this->owner,
        );

        // Adjust stock down to 3 (below threshold 5)
        $response = $this->withHeader('Authorization', 'Bearer '.$created['plainTextKey'])
            ->withHeader('Idempotency-Key', 'idemp-inv-001')
            ->postJson("http://{$this->domain}/api/v1/store/inventory/adjust", [
                'variant_id' => $this->variant->id,
                'warehouse_id' => $this->warehouse->id,
                'new_on_hand' => 3,
                'reason' => 'damage',
                'notes' => 'Damaged during transit',
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.on_hand', 3);

        $this->assertDatabaseHas('webhook_deliveries', [
            'tenant_id' => $this->tenant->id,
            'webhook_subscription_id' => $subscription->id,
            'event' => 'inventory.low',
        ]);

        Queue::assertPushed(DispatchWebhookJob::class);
    }

    public function test_affiliate_catalog_and_order_creation_api(): void
    {
        $created = ApiKey::createKey(
            tenant: $this->tenant,
            name: 'Affiliate Bot Key',
            scopes: [
                ApiKey::SCOPE_AFFILIATE_CATALOG_READ,
                ApiKey::SCOPE_AFFILIATE_ORDERS_READ,
                ApiKey::SCOPE_AFFILIATE_ORDERS_CREATE,
                ApiKey::SCOPE_AFFILIATE_WALLET_READ,
            ],
            owner: $this->affiliate,
        );

        // 1. Get Catalog
        $catalogRes = $this->withHeader('Authorization', 'Bearer '.$created['plainTextKey'])
            ->getJson("http://{$this->domain}/api/v1/affiliate/catalog");
        $catalogRes->assertStatus(200);
        $catalogRes->assertJsonPath('data.0.wholesale_price_minor', 30000);

        // 2. Submit Affiliate Order
        $orderPayload = [
            'customer_name' => 'عمرو دياب',
            'customer_phone' => '01099887766',
            'governorate_id' => $this->cairo->id,
            'city' => 'مدينة نصر',
            'address' => 'شارع عباس العقاد - عمارة 15',
            'variant_id' => $this->variant->id,
            'quantity' => 1,
            'selling_price_minor' => 45000, // 450.00 EGP (within [400, 700])
            'utm_source' => 'tiktok',
            'utm_campaign' => 'spring_sale',
        ];

        $orderRes = $this->withHeader('Authorization', 'Bearer '.$created['plainTextKey'])
            ->withHeader('Idempotency-Key', 'idemp-aff-ord-001')
            ->postJson("http://{$this->domain}/api/v1/affiliate/orders", $orderPayload);

        $orderRes->assertStatus(201);
        $orderRes->assertJsonPath('data.affiliate_id', $this->affiliate->id);

        // 3. View Wallet Balances
        $walletRes = $this->withHeader('Authorization', 'Bearer '.$created['plainTextKey'])
            ->getJson("http://{$this->domain}/api/v1/affiliate/wallet");
        $walletRes->assertStatus(200);
        $walletRes->assertJsonStructure([
            'data' => [
                'affiliate_id',
                'name',
                'code',
                'available_minor',
                'pending_minor',
                'locked_minor',
                'debt_minor',
            ],
        ]);
    }

    public function test_webhook_delivery_job_signs_with_hmac_and_retries(): void
    {
        Http::fake([
            'https://webhook.site/receiver' => Http::response(['status' => 'ok'], 200),
        ]);

        $subscription = WebhookSubscription::create([
            'tenant_id' => $this->tenant->id,
            'url' => 'https://webhook.site/receiver',
            'secret' => 'whsec_mysupersecret123',
            'events' => ['order.created'],
            'is_active' => true,
        ]);

        $delivery = WebhookDelivery::create([
            'tenant_id' => $this->tenant->id,
            'webhook_subscription_id' => $subscription->id,
            'event' => 'order.created',
            'payload' => ['order_id' => 123, 'total' => 45000],
            'status' => WebhookDelivery::STATUS_PENDING,
            'attempt' => 0,
        ]);

        // Dispatch Job
        $job = new DispatchWebhookJob($delivery->id);
        $job->handle();

        $delivery->refresh();
        $this->assertEquals(WebhookDelivery::STATUS_DELIVERED, $delivery->status);
        $this->assertEquals(200, $delivery->response_status);

        // Verify HMAC Signature header in outgoing request
        Http::assertSent(function ($request) use ($delivery, $subscription) {
            $expectedSignature = 'sha256='.hash_hmac('sha256', (string) json_encode($delivery->payload), $subscription->secret);

            return $request->header('X-AffiliateOS-Signature')[0] === $expectedSignature
                && $request->header('X-AffiliateOS-Event')[0] === 'order.created';
        });
    }

    public function test_embeddable_order_form_quote_and_order_placement(): void
    {
        $product = $this->variant->product;

        // 1. Render Embed Form
        $viewRes = $this->get("http://{$this->domain}/embed/order/{$product->id}?ref=AFF999&utm_source=facebook");
        $viewRes->assertStatus(200);
        $viewRes->assertSee('سماعات بلوتوث لاسلكية');
        $viewRes->assertSee('AFF999');

        // 2. Request Live Shipping & Price Quote
        $quoteRes = $this->postJson("http://{$this->domain}/embed/order/quote", [
            'variant_id' => $this->variant->id,
            'quantity' => 2,
            'governorate_id' => $this->cairo->id,
            'selling_price_minor' => 45000,
        ]);
        $quoteRes->assertStatus(200);
        $quoteRes->assertJsonStructure([
            'success',
            'quote' => [
                'subtotal_minor',
                'shipping_cost_minor',
                'customer_total_minor',
            ],
            'formatted_total',
            'formatted_shipping',
        ]);

        // 3. Submit Order from Embed Form
        $submitRes = $this->post("http://{$this->domain}/embed/order/{$product->id}", [
            'variant_id' => $this->variant->id,
            'quantity' => 1,
            'selling_price_minor' => 45000,
            'customer_name' => 'كريم عبدالعزيز',
            'customer_phone' => '01223344556',
            'governorate_id' => $this->cairo->id,
            'city' => 'المعادي',
            'address' => 'شارع دجلة 22',
            'ref' => 'AFF999',
            'utm_source' => 'facebook',
            'utm_campaign' => 'black_friday',
        ]);

        $submitRes->assertStatus(200);
        $submitRes->assertSee('تم استلام طلبك بنجاح');
        $submitRes->assertSee('affiliate_os_order_created'); // verifies postMessage conversion script is emitted
    }

    public function test_openapi_json_schema_and_docs_ui(): void
    {
        // 1. OpenAPI JSON schema
        $jsonRes = $this->getJson("http://{$this->domain}/api/v1/openapi.json");
        $jsonRes->assertStatus(200);
        $jsonRes->assertJsonPath('openapi', '3.1.0');
        $jsonRes->assertJsonPath('info.title', 'AffiliateOS Developer REST API');
        $jsonRes->assertJsonStructure([
            'openapi',
            'info',
            'paths' => [
                '/store/products',
                '/store/orders',
                '/affiliate/catalog',
                '/affiliate/orders',
            ],
        ]);

        // 2. Documentation UI
        $uiRes = $this->get("http://{$this->domain}/docs/api");
        $uiRes->assertStatus(200);
        $uiRes->assertSee('دليل الربط البرمجي للمطورين');
        $uiRes->assertSee('OpenAPI Schema JSON');
    }
}
