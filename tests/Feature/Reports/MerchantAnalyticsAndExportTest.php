<?php

declare(strict_types=1);

namespace Tests\Feature\Reports;

use App\Domain\Orders\Enums\OrderStatus;
use App\Domain\Orders\Services\OrderTransitionService;
use App\Domain\Reports\Services\MerchantAnalyticsService;
use App\Models\Affiliate;
use App\Models\OrderEvent;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Enums\ReasonCode;
use App\Support\Tenancy\Permissions\TenantPermissionMatrix;
use Carbon\Carbon;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Lunar\Base\ValueObjects\Cart\TaxBreakdown;
use Lunar\FieldTypes\TranslatedText;
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

class MerchantAnalyticsAndExportTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected User $ownerUser;

    protected Currency $currency;

    protected Channel $channel;

    protected Country $country;

    protected ProductVariant $variantA;

    protected ProductVariant $variantB;

    protected Affiliate $topAffiliate;

    protected Affiliate $secondAffiliate;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PlanSeeder::class);

        $this->tenant = Tenant::create([
            'id' => 'analytics-merchant-store',
            'name' => 'Analytics Merchant Store',
            'status' => 'active',
        ]);
        $this->tenant->domains()->create(['domain' => 'analytics-merchant-store.localhost']);
        tenancy()->initialize($this->tenant);

        Language::firstOrCreate(['code' => 'ar'], ['name' => 'Arabic', 'default' => true]);

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
            'url' => 'http://analytics-merchant-store.localhost',
        ]);

        $this->ownerUser = User::create([
            'name' => 'Store Owner',
            'email' => 'owner@merchant-store.com',
            'password' => bcrypt('password123'),
            'tenant_id' => $this->tenant->id,
        ]);

        TenantPermissionMatrix::seedForTenant($this->tenant->id);
        TenantPermissionMatrix::assignRole($this->ownerUser, TenantPermissionMatrix::ROLE_OWNER, $this->tenant->id);

        $this->tenant->run(function () {
            $this->topAffiliate = Affiliate::create([
                'tenant_id' => $this->tenant->id,
                'name' => 'علي حسن',
                'email' => 'ali@affiliate.test',
                'phone' => '01011112222',
                'password' => bcrypt('password'),
                'code' => 'ALI100',
                'status' => 'active',
                'kyc_status' => 'approved',
            ]);

            $this->secondAffiliate = Affiliate::create([
                'tenant_id' => $this->tenant->id,
                'name' => 'سارة إبراهيم',
                'email' => 'sara@affiliate.test',
                'phone' => '01033334444',
                'password' => bcrypt('password'),
                'code' => 'SARA200',
                'status' => 'active',
                'kyc_status' => 'approved',
            ]);

            $productType = ProductType::firstOrCreate(['name' => 'Standard']);
            $taxClass = TaxClass::firstOrCreate(['name' => 'Standard']);

            $productA = Product::create([
                'product_type_id' => $productType->id,
                'attribute_data' => [
                    'name' => new TranslatedText(collect(['ar' => 'ساعة ذكية'])),
                ],
                'status' => 'published',
            ]);

            $this->variantA = ProductVariant::create([
                'product_id' => $productA->id,
                'tax_class_id' => $taxClass->id,
                'sku' => 'WATCH-01',
                'stock' => 50,
            ]);

            $productB = Product::create([
                'product_type_id' => $productType->id,
                'attribute_data' => [
                    'name' => new TranslatedText(collect(['ar' => 'باور بنك سريع'])),
                ],
                'status' => 'published',
            ]);

            $this->variantB = ProductVariant::create([
                'product_id' => $productB->id,
                'tax_class_id' => $taxClass->id,
                'sku' => 'PB-02',
                'stock' => 4, // low stock
            ]);
        });
    }

    public function test_merchant_analytics_service_calculates_all_reporting_dimensions(): void
    {
        $today = Carbon::today()->toDateString();
        OrderTransitionService::$isTransitioning = true;

        $this->tenant->run(function () use ($today) {
            // Order 1: Top affiliate, Variant A, Cairo, Delivered
            $this->createOrderWithDetails(
                $this->topAffiliate,
                $this->variantA,
                OrderStatus::DELIVERED->value,
                50000, 5000, 6000, 20000, 4000,
                'القاهرة',
                $today
            );

            // Order 2: Top affiliate, Variant A, Giza, Delivered
            $this->createOrderWithDetails(
                $this->topAffiliate,
                $this->variantA,
                OrderStatus::DELIVERED->value,
                50000, 5000, 6000, 20000, 4000,
                'الجيزة',
                $today
            );

            // Order 3: Second affiliate, Variant B, Alexandria, Returned (Refused at doorstep)
            $this->createOrderWithDetails(
                $this->secondAffiliate,
                $this->variantB,
                OrderStatus::RETURNED->value,
                30000, 5000, 0, 10000, 0,
                'الإسكندرية',
                $today,
                ReasonCode::REFUSED_AT_DOORSTEP->value
            );
        });

        OrderTransitionService::$isTransitioning = false;

        /** @var MerchantAnalyticsService $analyticsService */
        $analyticsService = app(MerchantAnalyticsService::class);

        // 1. Overview
        $overview = $analyticsService->getOverview('today', null, null, $this->tenant->id);
        $this->assertSame(3, $overview['orders_count']);
        $this->assertSame(2, $overview['delivered_orders_count']);
        $this->assertSame(1, $overview['returned_orders_count']);
        $this->assertSame(66.7, $overview['delivery_rate']);
        $this->assertSame(33.3, $overview['return_rate']);
        $this->assertSame(100000, $overview['gross_revenue_minor']); // 1,000 EGP
        $this->assertSame(12000, $overview['affiliate_commission_minor']);
        $this->assertSame(8000, $overview['tax_minor']);

        // 2. Top Products
        $topProducts = $analyticsService->getTopProducts('today', 5, null, null, $this->tenant->id);
        $this->assertNotEmpty($topProducts);
        $firstProduct = $topProducts->first();
        $this->assertSame('ساعة ذكية', $firstProduct['name']);
        $this->assertSame(2, $firstProduct['delivered_units']);
        $this->assertSame(100000, $firstProduct['total_revenue_minor']);

        // 3. Top Affiliates
        $topAffiliates = $analyticsService->getTopAffiliates('today', 5, null, null, $this->tenant->id);
        $this->assertNotEmpty($topAffiliates);
        $firstAffiliate = $topAffiliates->first();
        $this->assertSame('علي حسن', $firstAffiliate['name']);
        $this->assertSame(2, $firstAffiliate['delivered_count']);
        $this->assertSame(12000, $firstAffiliate['commission_minor']);

        // 4. Governorates
        $govs = $analyticsService->getGovernoratesBreakdown('today', null, null, $this->tenant->id);
        $cairoGov = $govs->firstWhere('governorate', 'القاهرة');
        $this->assertNotNull($cairoGov);
        $this->assertSame(1, $cairoGov['orders_count']);
        $this->assertSame(1, $cairoGov['delivered_count']);
        $this->assertSame(100.0, $cairoGov['delivery_rate']);

        // 5. Returns Breakdown
        $returns = $analyticsService->getReturnsBreakdown('today', null, null, $this->tenant->id);
        $this->assertSame(1, $returns['returned_count']);
        $refusedReason = collect($returns['reasons'])->firstWhere('code', ReasonCode::REFUSED_AT_DOORSTEP->value);
        $this->assertNotNull($refusedReason);
        $this->assertSame(1, $refusedReason['count']);
        $this->assertSame(100.0, $refusedReason['percentage']);

        // 6. Inventory Report
        $inventory = $analyticsService->getInventoryReport($this->tenant->id);
        $this->assertSame(2, $inventory['total_skus']);
        $this->assertSame(54, $inventory['total_units_on_hand']); // 50 + 4
        $this->assertSame(1, $inventory['low_stock_skus']); // Variant B has 4 stock <= 5
        $this->assertSame(0, $inventory['stockout_skus']);

        // 7. Tax Report
        $taxes = $analyticsService->getTaxReport('today', null, null, $this->tenant->id);
        $this->assertSame(100000, $taxes['gross_revenue_minor']);
        $this->assertSame(8000, $taxes['tax_collected_minor']);
        $this->assertSame(8.0, $taxes['effective_tax_rate']);
    }

    public function test_merchant_can_access_reports_dashboard_view(): void
    {
        $response = $this->actingAs($this->ownerUser, 'web')
            ->get("https://{$this->tenant->id}.localhost/store/reports?preset=today");

        $response->assertOk();
        $response->assertViewIs('store.reports.index');
        $response->assertSee('التقارير والتحليلات المالية والتشغيلية');
        $response->assertSee('تصدير المبيعات CSV');
    }

    public function test_reports_can_be_exported_to_csv_with_utf8_bom(): void
    {
        $today = Carbon::today()->toDateString();
        OrderTransitionService::$isTransitioning = true;

        $this->tenant->run(function () use ($today) {
            $this->createOrderWithDetails(
                $this->topAffiliate,
                $this->variantA,
                OrderStatus::DELIVERED->value,
                50000, 5000, 6000, 20000, 4000,
                'القاهرة',
                $today
            );
        });

        OrderTransitionService::$isTransitioning = false;

        $types = ['sales', 'products', 'affiliates', 'governorates', 'returns'];

        foreach ($types as $type) {
            $response = $this->actingAs($this->ownerUser, 'web')
                ->get("https://{$this->tenant->id}.localhost/store/reports/export/{$type}?preset=today");

            $response->assertOk();
            $this->assertStringContainsString('text/csv', (string) $response->headers->get('content-type'));
            $this->assertStringContainsString("report-{$type}-today", (string) $response->headers->get('content-disposition'));

            ob_start();
            $response->sendContent();
            $content = (string) ob_get_clean();

            // Assert UTF-8 BOM is present at the beginning of stream
            $this->assertStringStartsWith("\xEF\xBB\xBF", $content, "Export {$type} must start with UTF-8 BOM");
        }
    }

    protected function createOrderWithDetails(
        Affiliate $affiliate,
        ProductVariant $variant,
        string $status,
        int $totalMinor,
        int $shippingMinor,
        int $affiliateProfitMinor,
        int $wholesaleMinor,
        int $taxMinor,
        string $city,
        string $date,
        ?string $reasonCode = null
    ): Order {
        $order = Order::create([
            'tenant_id' => $this->tenant->id,
            'affiliate_id' => $affiliate->id,
            'channel_id' => $this->channel->id,
            'currency_code' => $this->currency->code,
            'status' => $status,
            'reference' => 'REP-'.bin2hex(random_bytes(4)),
            'customer_reference' => 'CUST-'.bin2hex(random_bytes(3)),
            'sub_total' => $totalMinor - $shippingMinor,
            'total' => $totalMinor,
            'shipping_total' => $shippingMinor,
            'discount_total' => 0,
            'tax_total' => $taxMinor,
            'tax_breakdown' => new TaxBreakdown,
            'affiliate_profit_minor' => $affiliateProfitMinor,
            'placed_at' => Carbon::parse($date)->midDay(),
            'created_at' => Carbon::parse($date)->midDay(),
            'price_snapshot' => json_encode([
                'affiliate_id' => $affiliate->id,
                'selling_total_minor' => $totalMinor - $shippingMinor,
                'wholesale_total_minor' => $wholesaleMinor,
                'shipping_cost_minor' => $shippingMinor,
                'customer_total_minor' => $totalMinor,
                'affiliate_profit_minor' => $affiliateProfitMinor,
            ]),
        ]);

        OrderAddress::create([
            'order_id' => $order->id,
            'type' => 'shipping',
            'country_id' => $this->country->id,
            'first_name' => 'زبون',
            'last_name' => 'تجريبي',
            'line_one' => 'شارع رمسيس',
            'city' => $city,
            'state' => $city,
            'contact_phone' => '01011223344',
            'created_at' => Carbon::parse($date)->midDay(),
        ]);

        OrderLine::create([
            'order_id' => $order->id,
            'purchasable_type' => ProductVariant::class,
            'purchasable_id' => $variant->id,
            'type' => 'physical',
            'identifier' => $variant->sku,
            'description' => $variant->sku,
            'unit_quantity' => 1,
            'quantity' => 1,
            'unit_price' => $totalMinor - $shippingMinor,
            'sub_total' => $totalMinor - $shippingMinor,
            'discount_total' => 0,
            'tax_breakdown' => new TaxBreakdown,
            'tax_total' => $taxMinor,
            'total' => $totalMinor,
            'created_at' => Carbon::parse($date)->midDay(),
        ]);

        if ($reasonCode !== null) {
            OrderEvent::create([
                'tenant_id' => $this->tenant->id,
                'order_id' => $order->id,
                'from_status' => OrderStatus::OUT_FOR_DELIVERY->value,
                'to_status' => $status,
                'reason_code' => $reasonCode,
                'actor_type' => 'courier',
                'created_at' => Carbon::parse($date)->midDay(),
            ]);
        }

        DB::table('lunar_orders')
            ->where('id', $order->id)
            ->update(['created_at' => Carbon::parse($date)->midDay()]);

        return $order;
    }
}
