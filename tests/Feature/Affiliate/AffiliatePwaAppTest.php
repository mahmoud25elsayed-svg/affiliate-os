<?php

declare(strict_types=1);

namespace Tests\Feature\Affiliate;

use App\Domain\Finance\DTOs\EntryData;
use App\Domain\Finance\DTOs\LedgerLineData;
use App\Domain\Finance\Services\AccountResolver;
use App\Domain\Finance\Services\Ledger;
use App\Models\Affiliate;
use App\Models\Order;
use App\Models\Tenant;
use App\Models\TrackingLink;
use App\Models\Warehouse;
use App\Models\WholesalePrice;
use App\Support\Location\Governorate;
use Database\Seeders\EgyptGovernoratesSeeder;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Lunar\FieldTypes\Text;
use Lunar\Models\Currency;
use Lunar\Models\Price;
use Lunar\Models\Product;
use Lunar\Models\ProductType;
use Lunar\Models\ProductVariant;
use Lunar\Models\TaxClass;
use Tests\TestCase;

class AffiliatePwaAppTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected string $domain;

    protected Affiliate $affiliate;

    protected ProductVariant $variant;

    protected Product $product;

    protected Governorate $cairo;

    protected function setUp(): void
    {
        parent::setUp();

        app()->setLocale('ar');
        $this->seed(PlanSeeder::class);
        $this->seed(EgyptGovernoratesSeeder::class);

        $this->tenant = $this->ensureTenantExists('pwaappstore');
        $this->domain = $this->tenant->domains->first()?->domain ?? 'pwaappstore.localhost';

        $this->tenant->run(function () {
            $this->cairo = Governorate::where('code', 'CAI')->firstOrFail();

            Warehouse::firstOrCreate([
                'tenant_id' => $this->tenant->id,
                'code' => 'MAIN',
            ], [
                'name' => 'المستودع الرئيسي',
                'type' => 'physical',
                'is_default' => true,
                'is_active' => true,
            ]);

            // Create active affiliate
            $this->affiliate = Affiliate::create([
                'name' => 'مسوق تجريبي',
                'phone' => '+201012345678',
                'email' => 'affiliate_pwa@test.com',
                'password' => 'secret123',
                'code' => 'PWA123',
                'status' => Affiliate::STATUS_ACTIVE,
                'phone_verified_at' => now(),
                'kyc_status' => Affiliate::KYC_APPROVED,
                'payout_method' => Affiliate::PAYOUT_METHOD_WALLET,
                'payout_details' => ['wallet_provider' => 'vodafone_cash', 'phone' => '01012345678'],
            ]);

            $productType = ProductType::first() ?? ProductType::create(['name' => 'Standard']);

            $this->product = Product::create([
                'product_type_id' => $productType->id,
                'status' => 'published',
                'attribute_data' => [
                    'name' => new Text('منتج بي دبليو إيه التجريبي'),
                    'description' => new Text('وصف تجريبي ممتاز'),
                ],
            ]);

            $taxClass = TaxClass::first() ?? TaxClass::create(['name' => 'Standard']);

            $this->variant = ProductVariant::create([
                'product_id' => $this->product->id,
                'sku' => 'SKU-PWA-01',
                'tax_class_id' => $taxClass->id,
                'stock' => 50,
                'backorder' => 0,
                'purchasable' => 'always',
                'unit_quantity' => 1,
            ]);

            $currency = Currency::first() ?? Currency::create([
                'code' => 'EGP',
                'name' => 'Egyptian Pound',
                'exchange_rate' => 1,
                'decimal_places' => 2,
                'enabled' => true,
                'default' => true,
            ]);

            Price::create([
                'price' => 25000, // 250 EGP
                'priceable_type' => ProductVariant::morphName(),
                'priceable_id' => $this->variant->id,
                'currency_id' => $currency->id,
                'min_quantity' => 1,
            ]);

            WholesalePrice::create([
                'product_variant_id' => $this->variant->id,
                'wholesale_price_minor' => 12000, // 120 EGP wholesale
                'min_price_minor' => 15000,       // 150 EGP min
                'max_price_minor' => 30000,       // 300 EGP max
                'status' => WholesalePrice::STATUS_ACTIVE,
            ]);
        });
    }

    public function test_unauthenticated_user_is_redirected_to_login(): void
    {
        $response = $this->get("https://{$this->domain}/partners/app");
        $response->assertRedirect('/partners/login');
    }

    public function test_dashboard_renders_inertia_with_balances_and_stats(): void
    {
        $response = $this->actingAs($this->affiliate, 'affiliate')
            ->get("https://{$this->domain}/partners/app");

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('App/Dashboard')
            ->has('affiliate')
            ->has('balances')
            ->has('stats')
            ->has('settings')
            ->has('recentOrders')
            ->where('affiliate.code', 'PWA123')
        );
    }

    public function test_catalog_renders_inertia_products(): void
    {
        $response = $this->actingAs($this->affiliate, 'affiliate')
            ->get("https://{$this->domain}/partners/app/catalog");

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('App/Catalog')
            ->has('products.data')
            ->has('categories')
            ->has('settings')
        );
    }

    public function test_product_detail_renders_honest_profit_calculator(): void
    {
        $response = $this->actingAs($this->affiliate, 'affiliate')
            ->get("https://{$this->domain}/partners/app/catalog/{$this->product->id}");

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('App/ProductDetail')
            ->has('product')
            ->where('product.wholesale_minor', 12000)
            ->has('product.ad_copies')
            ->has('settings')
        );
    }

    public function test_create_order_renders_and_quote_endpoint_works(): void
    {
        // 1. Render create order page
        $renderRes = $this->actingAs($this->affiliate, 'affiliate')
            ->get("https://{$this->domain}/partners/app/orders/create");

        $renderRes->assertOk();
        $renderRes->assertInertia(fn (Assert $page) => $page
            ->component('App/CreateOrder')
            ->has('products')
            ->has('governorates')
        );

        // 2. Test live price quote API
        $quoteRes = $this->actingAs($this->affiliate, 'affiliate')
            ->postJson("https://{$this->domain}/partners/app/orders/quote", [
                'variant_id' => $this->variant->id,
                'quantity' => 2,
                'selling_price_minor' => 20000, // 200 EGP per item
                'governorate_id' => $this->cairo->id,
            ]);

        $quoteRes->assertOk();
        $quoteRes->assertJsonStructure([
            'wholesale_total_minor',
            'shipping_cost_minor',
            'cod_fee_minor',
            'affiliate_profit_minor',
            'customer_total_minor',
            'warnings',
        ]);

        // Wholesale for 2 is 24000, selling is 40000 => profit is 16000
        $quoteRes->assertJson([
            'wholesale_total_minor' => 24000,
            'affiliate_profit_minor' => 16000,
        ]);
    }

    public function test_store_order_submits_affiliate_order(): void
    {
        $response = $this->actingAs($this->affiliate, 'affiliate')
            ->post("https://{$this->domain}/partners/app/orders", [
                'variant_id' => $this->variant->id,
                'quantity' => 1,
                'selling_price_minor' => 22000,
                'customer_name' => 'أحمد طارق محمد',
                'customer_phone' => '01099887766',
                'governorate_id' => $this->cairo->id,
                'address' => 'شارع التحرير مبنى 12 الشقة 4',
                'notes' => 'يرجى الاتصال قبل التسليم',
            ]);

        $response->assertSessionHasNoErrors();
        $order = \Lunar\Models\Order::latest('id')->first();
        $this->assertNotNull($order);
        $this->assertEquals($this->affiliate->id, $order->affiliate_id);

        $response->assertRedirect("/partners/app/orders/{$order->reference}");
    }

    public function test_order_detail_renders_with_timeline(): void
    {
        $this->actingAs($this->affiliate, 'affiliate')
            ->post("https://{$this->domain}/partners/app/orders", [
                'variant_id' => $this->variant->id,
                'quantity' => 1,
                'selling_price_minor' => 22000,
                'customer_name' => 'مصطفى كمال',
                'customer_phone' => '01122334455',
                'governorate_id' => $this->cairo->id,
                'address' => 'مدينة نصر الحي السابع',
            ]);

        $order = \Lunar\Models\Order::latest('id')->firstOrFail();

        $response = $this->actingAs($this->affiliate, 'affiliate')
            ->get("https://{$this->domain}/partners/app/orders/{$order->reference}");

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('App/OrderDetail')
            ->has('order')
            ->has('timeline')
            ->where('order.reference', $order->reference)
        );
    }

    public function test_wallet_renders_and_payout_can_be_requested(): void
    {
        // Fund the affiliate's available account via Ledger
        $this->tenant->run(function () {
            $ledger = app(Ledger::class);
            $resolver = app(AccountResolver::class);
            $availableAccount = $resolver->getAffiliateAvailableAccount($this->affiliate);
            $revenueAccount = $resolver->getStoreRevenueAccount();

            $ledger->post(new EntryData(
                type: 'initial_deposit',
                idempotencyKey: 'test_deposit_'.uniqid(),
                description: 'رصيد تمويلي للاختبار',
                lines: [
                    new LedgerLineData($revenueAccount, -25000),
                    new LedgerLineData($availableAccount, 25000),
                ]
            ));
        });

        $this->assertEquals(25000, $this->affiliate->getAvailableBalance());

        // 1. Check wallet page renders
        $response = $this->actingAs($this->affiliate, 'affiliate')
            ->get("https://{$this->domain}/partners/app/wallet");

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('App/Wallet')
            ->has('balances')
            ->has('movements.data')
            ->where('balances.available', 25000)
        );

        // 2. Request Payout
        $payoutRes = $this->actingAs($this->affiliate, 'affiliate')
            ->post("https://{$this->domain}/partners/app/wallet/payout", [
                'amount_minor' => 15000, // 150 EGP
            ]);

        $payoutRes->assertSessionHasNoErrors();
        $this->assertEquals(10000, $this->affiliate->getAvailableBalance());
        $this->assertEquals(15000, $this->affiliate->getLockedBalance());
    }

    public function test_links_crud_works_in_app(): void
    {
        // 1. Render links page
        $renderRes = $this->actingAs($this->affiliate, 'affiliate')
            ->get("https://{$this->domain}/partners/app/links");

        $renderRes->assertOk();
        $renderRes->assertInertia(fn (Assert $page) => $page
            ->component('App/Links')
            ->has('links')
            ->has('products')
            ->has('defaultStorefrontUrl')
        );

        // 2. Create tracking link
        $createRes = $this->actingAs($this->affiliate, 'affiliate')
            ->post("https://{$this->domain}/partners/app/links", [
                'title' => 'حملة تيك توك كوتشي',
                'product_id' => $this->product->id,
                'custom_selling_price' => 200,
                'custom_code' => 'TIKTOK26',
            ]);

        $createRes->assertSessionHasNoErrors();
        $link = TrackingLink::where('code', 'TIKTOK26')->first();
        $this->assertNotNull($link);
        $this->assertEquals($this->affiliate->id, $link->affiliate_id);

        // 3. Delete link
        $deleteRes = $this->actingAs($this->affiliate, 'affiliate')
            ->delete("https://{$this->domain}/partners/app/links/{$link->id}");

        $deleteRes->assertSessionHasNoErrors();
        $this->assertNull(TrackingLink::find($link->id));
    }

    public function test_analytics_renders_breakdown_reports(): void
    {
        $response = $this->actingAs($this->affiliate, 'affiliate')
            ->get("https://{$this->domain}/partners/app/analytics");

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('App/Analytics')
            ->has('overview')
            ->has('byProduct')
            ->has('byGovernorate')
            ->has('bySource')
        );
    }

    public function test_profile_renders_and_updates_successfully(): void
    {
        // 1. Render profile
        $response = $this->actingAs($this->affiliate, 'affiliate')
            ->get("https://{$this->domain}/partners/app/profile");

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('App/Profile')
            ->has('affiliate')
            ->where('affiliate.code', 'PWA123')
        );

        // 2. Update basic info
        $updateRes = $this->actingAs($this->affiliate, 'affiliate')
            ->post("https://{$this->domain}/partners/app/profile", [
                'name' => 'مسوق محترف جديد',
                'email' => 'new_pwa_affiliate@test.com',
            ]);

        $updateRes->assertSessionHasNoErrors();
        $this->affiliate->refresh();
        $this->assertEquals('مسوق محترف جديد', $this->affiliate->name);
        $this->assertEquals('new_pwa_affiliate@test.com', $this->affiliate->email);
    }
}
