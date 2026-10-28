<?php

declare(strict_types=1);

namespace Tests\Feature\Affiliate;

use App\Domain\Affiliate\Services\AffiliateTrackingService;
use App\Domain\Checkout\DTOs\CheckoutPayload;
use App\Domain\Checkout\Services\OrderCreationService;
use App\Http\Controllers\Affiliate\AffiliateTrackingLinkController;
use App\Models\Affiliate;
use App\Models\AffiliateClick;
use App\Models\InventoryBalance;
use App\Models\Tenant;
use App\Models\TrackingLink;
use App\Models\Warehouse;
use App\Models\WholesalePrice;
use App\Support\Location\Governorate;
use Database\Seeders\EgyptGovernoratesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Lunar\FieldTypes\Text;
use Lunar\Models\Channel;
use Lunar\Models\Currency;
use Lunar\Models\Customer;
use Lunar\Models\Language;
use Lunar\Models\Order;
use Lunar\Models\Product;
use Lunar\Models\ProductType;
use Lunar\Models\ProductVariant;
use Lunar\Models\TaxClass;
use Tests\TestCase;

class AffiliateTrackingAndAttributionTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected string $domain = 'track.affiliate.test';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(EgyptGovernoratesSeeder::class);

        /** @var Tenant $tenant */
        $tenant = Tenant::create([
            'id' => 'test-tenant-track',
            'name' => 'متجر التتبع للاختبار',
        ]);
        $this->tenant = $tenant;

        $this->tenant->domains()->create([
            'domain' => $this->domain,
        ]);

        $this->tenant->run(function () {
            Channel::firstOrCreate(
                ['handle' => 'webstore'],
                ['name' => 'Webstore', 'default' => true, 'url' => 'https://track.affiliate.test']
            );

            Currency::firstOrCreate(
                ['code' => 'EGP'],
                ['name' => 'Egyptian Pound', 'exchange_rate' => 1.0, 'decimal_places' => 2, 'enabled' => true, 'default' => true]
            );

            TaxClass::firstOrCreate(['name' => 'Default'], ['name' => 'Default']);

            Language::firstOrCreate(
                ['code' => 'ar'],
                ['name' => 'Arabic', 'default' => true]
            );

            $cairo = Governorate::where('code', 'CAI')->first();
            $governorateId = $cairo instanceof Governorate ? $cairo->id : 1;

            Warehouse::firstOrCreate(
                ['code' => 'MAIN'],
                [
                    'name' => 'المستودع الرئيسي',
                    'governorate_id' => $governorateId,
                    'city' => 'القاهرة',
                    'address' => 'شارع رمسيس',
                    'contact_phone' => '+201011112222',
                    'priority' => 1,
                    'is_active' => true,
                ]
            );
        });
    }

    protected function createProductWithWholesale(int $wholesalePriceMinor = 15000): ProductVariant
    {
        $productType = ProductType::first() ?? ProductType::create(['name' => 'Default']);

        $product = Product::create([
            'product_type_id' => $productType->id,
            'status' => 'published',
            'attribute_data' => ['name' => new Text('ساعة ذكية فاخرة')],
        ]);

        /** @var ProductVariant $variant */
        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'tax_class_id' => 1,
            'sku' => 'WATCH-SMART-'.uniqid(),
            'unit_quantity' => 1,
        ]);

        WholesalePrice::create([
            'product_variant_id' => $variant->id,
            'wholesale_price_minor' => $wholesalePriceMinor,
            'min_selling_price_minor' => $wholesalePriceMinor + 2000,   // 170.00 EGP
            'max_selling_price_minor' => $wholesalePriceMinor + 10000,  // 250.00 EGP
            'status' => 'active',
        ]);

        $warehouse = Warehouse::first();
        if ($warehouse) {
            InventoryBalance::create([
                'warehouse_id' => $warehouse->id,
                'product_variant_id' => $variant->id,
                'on_hand' => 100,
                'allocated' => 0,
                'available' => 100,
            ]);
        }

        return $variant;
    }

    public function test_tracking_link_creation_and_bounds_validation(): void
    {
        $this->tenant->run(function () {
            /** @var Affiliate $affiliate */
            $affiliate = Affiliate::create([
                'name' => 'أحمد إبراهيم',
                'phone' => '+201011114444',
                'password' => 'secret123',
                'code' => 'AHMED1',
                'status' => Affiliate::STATUS_ACTIVE,
            ]);

            $variant = $this->createProductWithWholesale(20000); // wholesale: 200 EGP, min: 220 EGP, max: 300 EGP
            $product = $variant->product;

            $this->actingAs($affiliate, 'affiliate');

            // 1. Valid Creation with bounds check
            $response = $this->post("http://{$this->domain}/partners/links", [
                'product_id' => $product->id,
                'title' => 'حملة تيك توك 1',
                'custom_selling_price' => 250.00, // Valid (between 220 and 300)
                'custom_code' => 'TIKTOK250',
            ]);

            $response->assertRedirect("http://{$this->domain}/partners/links");
            $this->assertDatabaseHas('tracking_links', [
                'affiliate_id' => $affiliate->id,
                'product_id' => $product->id,
                'code' => 'TIKTOK250',
                'custom_selling_price_minor' => 25000,
            ]);

            /** @var TrackingLink $link */
            $link = TrackingLink::where('code', 'TIKTOK250')->firstOrFail();
            $this->assertStringContainsString('TIKTOK250', $link->getQrCodeSvg());

            // 2. Reject Selling Price Below Min
            $this->expectException(ValidationException::class);
            $controller = app(AffiliateTrackingLinkController::class);
            $req = Request::create("http://{$this->domain}/partners/links", 'POST', [
                'product_id' => $product->id,
                'custom_selling_price' => 190.00, // Below min 220.00
            ]);
            $controller->store($req);
        });
    }

    public function test_storefront_referral_redirect_records_click_and_sets_cookie(): void
    {
        $this->tenant->run(function () {
            /** @var Affiliate $affiliate */
            $affiliate = Affiliate::create([
                'name' => 'سارة علي',
                'phone' => '+201122223333',
                'password' => 'secret123',
                'code' => 'SARA1',
                'status' => Affiliate::STATUS_ACTIVE,
            ]);

            $variant = $this->createProductWithWholesale(10000);
            $product = $variant->product;

            /** @var TrackingLink $link */
            $link = TrackingLink::create([
                'tenant_id' => (string) $this->tenant->id,
                'affiliate_id' => $affiliate->id,
                'product_id' => $product->id,
                'code' => 'SARAPROD',
                'title' => 'رابط سارة',
                'is_active' => true,
            ]);

            $response = $this->get("http://{$this->domain}/ref/SARAPROD?utm_source=tiktok&utm_campaign=summer");

            $response->assertStatus(302);
            $response->assertCookie(AffiliateTrackingService::COOKIE_NAME);

            // Assert click recorded in database
            $this->assertDatabaseHas('affiliate_clicks', [
                'tracking_link_id' => $link->id,
                'affiliate_id' => $affiliate->id,
                'utm_source' => 'tiktok',
                'utm_campaign' => 'summer',
            ]);

            $link->refresh();
            $this->assertSame(1, $link->clicks_count);
        });
    }

    public function test_storefront_order_attributed_to_affiliate_via_cookie(): void
    {
        $this->tenant->run(function () {
            /** @var Affiliate $affiliate */
            $affiliate = Affiliate::create([
                'name' => 'طارق كمال',
                'phone' => '+201099998888',
                'email' => 'tarek@affiliate.test',
                'password' => 'secret123',
                'code' => 'TAREK88',
                'status' => Affiliate::STATUS_ACTIVE,
            ]);

            $variant = $this->createProductWithWholesale(12000); // 120.00 EGP wholesale
            $trackingService = app(AffiliateTrackingService::class);

            /** @var TrackingLink $link */
            $link = TrackingLink::create([
                'tenant_id' => (string) $this->tenant->id,
                'affiliate_id' => $affiliate->id,
                'product_id' => $variant->product_id,
                'product_variant_id' => $variant->id,
                'code' => 'TAREKWATCH',
                'custom_selling_price_minor' => 16000, // 160.00 EGP selling price (profit = 40.00 EGP)
                'is_active' => true,
            ]);

            $token = $trackingService->generateTokenPayload($link);

            $orderCreationService = app(OrderCreationService::class);

            $cairoId = Governorate::where('code', 'CAI')->firstOrFail()->id;

            $payload = new CheckoutPayload(
                customer_name: 'محمد عبد الله',
                customer_phone: '01012345678', // Different from affiliate phone
                governorate_id: $cairoId,
                city: 'مدينة نصر',
                address: 'شارع عباس العقاد عمارة 15',
                notes: 'يرجى الاتصال قبل الوصول',
                payment_method: 'cod',
                items: [
                    ['purchasable_id' => $variant->id, 'quantity' => 1],
                ],
                idempotency_token: 'order_attr_test_1',
                affiliate_ref_token: $token
            );

            $order = $orderCreationService->create($payload);

            $this->assertSame($affiliate->id, (int) $order->getAttribute('affiliate_id'));
            $this->assertSame($link->id, (int) $order->getAttribute('tracking_link_id'));

            $priceSnapshot = json_decode((string) $order->getAttribute('price_snapshot'), true);
            $this->assertSame('tracking_link', $priceSnapshot['attribution_type']);
            // Profit is 40.00 EGP = 4000 minor
            $this->assertSame(4000, $priceSnapshot['affiliate_profit_minor']);

            $link->refresh();
            $this->assertSame(1, $link->orders_count);
        });
    }

    public function test_anti_self_referral_blocks_affiliate_earning_on_own_order(): void
    {
        $this->tenant->run(function () {
            /** @var Affiliate $affiliate */
            $affiliate = Affiliate::create([
                'name' => 'يوسف الشريف',
                'phone' => '+201000001111',
                'email' => 'youssef@test.com',
                'password' => 'secret123',
                'code' => 'YOUSSEF1',
                'status' => Affiliate::STATUS_ACTIVE,
            ]);

            $variant = $this->createProductWithWholesale(10000);
            $trackingService = app(AffiliateTrackingService::class);

            /** @var TrackingLink $link */
            $link = TrackingLink::create([
                'tenant_id' => (string) $this->tenant->id,
                'affiliate_id' => $affiliate->id,
                'product_id' => $variant->product_id,
                'code' => 'YOUSSEFLINK',
                'is_active' => true,
            ]);

            $token = $trackingService->generateTokenPayload($link);
            $orderCreationService = app(OrderCreationService::class);

            $cairoId = Governorate::where('code', 'CAI')->firstOrFail()->id;

            // Customer attempts to order using the affiliate's exact same phone number
            $payload = new CheckoutPayload(
                customer_name: 'يوسف الشريف نفسه',
                customer_phone: '01000001111', // Matches affiliate phone
                governorate_id: $cairoId,
                city: 'المعادي',
                address: 'شارع 9 عمارة 4',
                payment_method: 'cod',
                items: [
                    ['purchasable_id' => $variant->id, 'quantity' => 1],
                ],
                idempotency_token: 'self_ref_test_1',
                affiliate_ref_token: $token
            );

            $order = $orderCreationService->create($payload);

            // Attribution is blocked due to self-referral!
            $this->assertNull($order->getAttribute('affiliate_id'));
            $this->assertNull($order->getAttribute('tracking_link_id'));

            /** @var array<string, mixed> $meta */
            $meta = $order->getAttribute('meta');
            $this->assertTrue($meta['self_referral_blocked'] ?? false);
        });
    }

    public function test_click_velocity_rate_limiting_prevents_fraudulent_clicks(): void
    {
        $this->tenant->run(function () {
            /** @var Affiliate $affiliate */
            $affiliate = Affiliate::create([
                'name' => 'محمود فوزي',
                'phone' => '+201088887777',
                'password' => 'secret123',
                'code' => 'FAWZY',
                'status' => Affiliate::STATUS_ACTIVE,
            ]);

            /** @var TrackingLink $link */
            $link = TrackingLink::create([
                'tenant_id' => (string) $this->tenant->id,
                'affiliate_id' => $affiliate->id,
                'code' => 'CLICKLIMIT',
                'is_active' => true,
            ]);

            $trackingService = app(AffiliateTrackingService::class);
            $request = Request::create('/ref/CLICKLIMIT', 'GET', [], [], [], [
                'REMOTE_ADDR' => '192.168.1.100',
            ]);

            // Simulate 30 clicks from the same IP
            for ($i = 0; $i < 30; $i++) {
                $click = $trackingService->recordClick($link, $request);
                $this->assertNotNull($click);
            }

            $link->refresh();
            $this->assertSame(30, $link->clicks_count);

            // 31st click from the same IP within the hour should be blocked!
            $blockedClick = $trackingService->recordClick($link, $request);
            $this->assertNull($blockedClick);

            $link->refresh();
            $this->assertSame(30, $link->clicks_count);
        });
    }

    public function test_expired_attribution_cookie_is_ignored(): void
    {
        $this->tenant->run(function () {
            /** @var Affiliate $affiliate */
            $affiliate = Affiliate::create([
                'name' => 'هاني رمزي',
                'phone' => '+201066665555',
                'password' => 'secret123',
                'code' => 'HANY1',
                'status' => Affiliate::STATUS_ACTIVE,
            ]);

            /** @var TrackingLink $link */
            $link = TrackingLink::create([
                'tenant_id' => (string) $this->tenant->id,
                'affiliate_id' => $affiliate->id,
                'code' => 'HANYEXPIRED',
                'is_active' => true,
            ]);

            $trackingService = app(AffiliateTrackingService::class);

            // Generate token 35 days ago (window is 30 days)
            $oldTimestamp = Carbon::now()->subDays(35)->timestamp;
            $appKey = (string) config('app.key');
            $data = "{$link->id}:{$link->affiliate_id}:{$oldTimestamp}";
            $signature = hash_hmac('sha256', $data, $appKey);
            $expiredToken = base64_encode("{$data}:{$signature}");

            $resolved = $trackingService->resolveAttributedLink($expiredToken);
            $this->assertNull($resolved);
        });
    }

    public function test_prune_clicks_artisan_command_deletes_old_clicks(): void
    {
        $this->tenant->run(function () {
            /** @var Affiliate $affiliate */
            $affiliate = Affiliate::create([
                'name' => 'كريم عصام',
                'phone' => '+201044443333',
                'password' => 'secret123',
                'code' => 'KARIM1',
                'status' => Affiliate::STATUS_ACTIVE,
            ]);

            /** @var TrackingLink $link */
            $link = TrackingLink::create([
                'tenant_id' => (string) $this->tenant->id,
                'affiliate_id' => $affiliate->id,
                'code' => 'PRUNECLICK',
                'is_active' => true,
            ]);

            // Create an old click (100 days old)
            DB::table('affiliate_clicks')->insert([
                'tenant_id' => (string) $this->tenant->id,
                'tracking_link_id' => $link->id,
                'affiliate_id' => $affiliate->id,
                'ip_hash' => 'old_hash',
                'created_at' => Carbon::now()->subDays(100),
            ]);

            // Create a recent click (5 days old)
            DB::table('affiliate_clicks')->insert([
                'tenant_id' => (string) $this->tenant->id,
                'tracking_link_id' => $link->id,
                'affiliate_id' => $affiliate->id,
                'ip_hash' => 'recent_hash',
                'created_at' => Carbon::now()->subDays(5),
            ]);

            $this->assertSame(2, AffiliateClick::count());

            $this->artisan('affiliate:prune-clicks', ['--days' => 90])
                ->assertExitCode(0);

            $this->assertSame(1, AffiliateClick::count());
            $this->assertDatabaseHas('affiliate_clicks', ['ip_hash' => 'recent_hash']);
            $this->assertDatabaseMissing('affiliate_clicks', ['ip_hash' => 'old_hash']);
        });
    }
}
