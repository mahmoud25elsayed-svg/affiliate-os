<?php

declare(strict_types=1);

namespace Tests\Feature\Reports;

use App\Domain\Orders\Enums\OrderStatus;
use App\Domain\Orders\Services\OrderTransitionService;
use App\Domain\Reports\Services\DailyMetricsAggregator;
use App\Models\Affiliate;
use App\Models\DailyStoreMetric;
use App\Models\OrderEvent;
use App\Models\Tenant;
use Carbon\Carbon;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
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

class DailyMetricsAggregationTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected Currency $currency;

    protected Channel $channel;

    protected Country $country;

    protected ProductVariant $variant;

    protected Affiliate $affiliate;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PlanSeeder::class);

        $this->tenant = Tenant::create([
            'id' => 'reports-store',
            'name' => 'Reports Test Store',
            'status' => 'active',
        ]);
        $this->tenant->domains()->create(['domain' => 'reports-store.localhost']);
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
            'url' => 'http://reports-store.localhost',
        ]);

        $this->tenant->run(function () {
            $this->affiliate = Affiliate::create([
                'tenant_id' => $this->tenant->id,
                'name' => 'مسوق تجريبي',
                'email' => 'affiliate.reports@test.com',
                'phone' => '01099887766',
                'password' => bcrypt('Password123!'),
                'code' => 'REP01',
                'status' => 'active',
                'kyc_status' => 'approved',
            ]);

            $productType = ProductType::firstOrCreate(['name' => 'Standard']);
            $taxClass = TaxClass::firstOrCreate(['name' => 'Standard']);

            $product = Product::create([
                'product_type_id' => $productType->id,
                'attribute_data' => [
                    'name' => new TranslatedText(collect(['ar' => 'سماعات بلوتوث'])),
                ],
                'status' => 'published',
            ]);

            $this->variant = ProductVariant::create([
                'product_id' => $product->id,
                'tax_class_id' => $taxClass->id,
                'sku' => 'EAR-001',
                'stock' => 100,
            ]);
        });
    }

    public function test_daily_metrics_rollup_strictly_matches_raw_direct_counts_and_financials(): void
    {
        $yesterday = Carbon::yesterday()->toDateString();
        $today = Carbon::today()->toDateString();

        OrderTransitionService::$isTransitioning = true;

        $this->tenant->run(function () use ($yesterday, $today) {
            // Day 1 (Yesterday): 2 Delivered, 1 Returned, 1 Cancelled
            // Delivered 1: 300 EGP total, 50 EGP shipping, 30 EGP affiliate, 100 EGP wholesale, 20 EGP tax
            $this->createTestOrder(OrderStatus::DELIVERED->value, 30000, 5000, 3000, 10000, 2000, $yesterday);
            // Delivered 2: 400 EGP total, 60 EGP shipping, 40 EGP affiliate, 150 EGP wholesale, 30 EGP tax
            $this->createTestOrder(OrderStatus::DELIVERED->value, 40000, 6000, 4000, 15000, 3000, $yesterday);
            // Returned 1: 250 EGP
            $this->createTestOrder(OrderStatus::RETURNED->value, 25000, 5000, 0, 8000, 0, $yesterday, 'refused_at_doorstep');
            // Cancelled 1: 200 EGP
            $this->createTestOrder(OrderStatus::CANCELLED_BY_CUSTOMER->value, 20000, 4000, 0, 7000, 0, $yesterday);

            // Day 2 (Today): 1 Delivered, 1 Confirmed, 1 Returning
            // Delivered 3: 500 EGP total, 50 EGP shipping, 60 EGP affiliate, 200 EGP wholesale, 40 EGP tax
            $this->createTestOrder(OrderStatus::DELIVERED->value, 50000, 5000, 6000, 20000, 4000, $today);
            // Confirmed 1: 350 EGP
            $this->createTestOrder(OrderStatus::CONFIRMED->value, 35000, 5000, 4000, 12000, 0, $today);
            // Returning 1: 280 EGP
            $this->createTestOrder(OrderStatus::RETURNING->value, 28000, 5000, 0, 9000, 0, $today, 'long_delivery_time');
        });

        OrderTransitionService::$isTransitioning = false;

        /** @var DailyMetricsAggregator $aggregator */
        $aggregator = app(DailyMetricsAggregator::class);

        // Run aggregation for both days
        $aggregator->aggregateForTenant($this->tenant, $yesterday);
        $aggregator->aggregateForTenant($this->tenant, $today);

        // Verify Acceptance Criteria: Daily aggregation rollups strictly equal raw direct database counts
        $directTotalOrders = Order::where('tenant_id', $this->tenant->id)->count();
        $aggregatedTotalOrders = (int) DailyStoreMetric::where('tenant_id', $this->tenant->id)->sum('orders_count');
        $this->assertSame($directTotalOrders, $aggregatedTotalOrders, 'Total orders count in rollup must match raw DB count');
        $this->assertSame(7, $aggregatedTotalOrders);

        // Delivered orders count
        $directDeliveredCount = Order::where('tenant_id', $this->tenant->id)
            ->whereIn('status', [OrderStatus::DELIVERED->value, OrderStatus::SETTLED->value])
            ->count();
        $aggregatedDeliveredCount = (int) DailyStoreMetric::where('tenant_id', $this->tenant->id)->sum('delivered_orders_count');
        $this->assertSame($directDeliveredCount, $aggregatedDeliveredCount);
        $this->assertSame(3, $aggregatedDeliveredCount);

        // Returned orders count
        $directReturnedCount = Order::where('tenant_id', $this->tenant->id)
            ->whereIn('status', [
                OrderStatus::RETURN_REQUESTED->value,
                OrderStatus::RETURNING->value,
                OrderStatus::RETURNED->value,
                OrderStatus::DELIVERY_FAILED->value,
            ])
            ->count();
        $aggregatedReturnedCount = (int) DailyStoreMetric::where('tenant_id', $this->tenant->id)->sum('returned_orders_count');
        $this->assertSame($directReturnedCount, $aggregatedReturnedCount);
        $this->assertSame(2, $aggregatedReturnedCount);

        // Gross revenue match
        $directDeliveredRevenue = (int) Order::where('tenant_id', $this->tenant->id)
            ->whereIn('status', [OrderStatus::DELIVERED->value, OrderStatus::SETTLED->value])
            ->sum('total');
        $aggregatedGrossRevenue = (int) DailyStoreMetric::where('tenant_id', $this->tenant->id)->sum('gross_revenue_minor');
        $this->assertSame($directDeliveredRevenue, $aggregatedGrossRevenue);
        $this->assertSame(30000 + 40000 + 50000, $aggregatedGrossRevenue); // 120,000 minor (1,200 EGP)

        // Affiliate commission match
        $directAffiliateCommission = (int) Order::where('tenant_id', $this->tenant->id)
            ->whereIn('status', [OrderStatus::DELIVERED->value, OrderStatus::SETTLED->value])
            ->sum('affiliate_profit_minor');
        $aggregatedAffiliateCommission = (int) DailyStoreMetric::where('tenant_id', $this->tenant->id)->sum('affiliate_commission_minor');
        $this->assertSame($directAffiliateCommission, $aggregatedAffiliateCommission);
        $this->assertSame(3000 + 4000 + 6000, $aggregatedAffiliateCommission); // 13,000 minor (130 EGP)

        // Tax match
        $directTaxTotal = (int) Order::where('tenant_id', $this->tenant->id)
            ->whereIn('status', [OrderStatus::DELIVERED->value, OrderStatus::SETTLED->value])
            ->sum('tax_total');
        $aggregatedTaxTotal = (int) DailyStoreMetric::where('tenant_id', $this->tenant->id)->sum('tax_minor');
        $this->assertSame($directTaxTotal, $aggregatedTaxTotal);
        $this->assertSame(2000 + 3000 + 4000, $aggregatedTaxTotal); // 9,000 minor (90 EGP)

        // Net profit calculation
        // Total Delivered Revenue: 120,000
        // Commissions: 13,000
        // Shipping: 5,000 + 6,000 + 5,000 = 16,000
        // COGS: 10,000 + 15,000 + 20,000 = 45,000
        // Tax: 9,000
        // Net profit = 120,000 - (13,000 + 16,000 + 45,000 + 9,000) = 37,000 minor (370.00 EGP)
        $aggregatedNetProfit = (int) DailyStoreMetric::where('tenant_id', $this->tenant->id)->sum('net_profit_minor');
        $this->assertSame(37000, $aggregatedNetProfit);
    }

    public function test_artisan_command_aggregates_daily_metrics(): void
    {
        OrderTransitionService::$isTransitioning = true;
        $yesterday = Carbon::yesterday()->toDateString();

        $this->tenant->run(function () use ($yesterday) {
            $this->createTestOrder(OrderStatus::DELIVERED->value, 35000, 5000, 3000, 10000, 2000, $yesterday);
        });
        OrderTransitionService::$isTransitioning = false;

        $exitCode = Artisan::call('reports:aggregate-daily', [
            '--date' => $yesterday,
            '--tenant' => $this->tenant->id,
        ]);

        $output = Artisan::output();
        $this->assertSame(0, $exitCode, "Command failed with output: {$output}");

        $metric = DailyStoreMetric::withoutGlobalScopes()
            ->where('tenant_id', $this->tenant->id)
            ->whereDate('metric_date', $yesterday)
            ->first();

        $this->assertNotNull($metric, "Metric not found. Output: {$output}. All metrics: ".DailyStoreMetric::withoutGlobalScopes()->get()->toJson());

        $this->assertNotNull($metric);
        $this->assertSame(1, $metric->orders_count);
        $this->assertSame(1, $metric->delivered_orders_count);
        $this->assertSame(35000, $metric->gross_revenue_minor);
    }

    protected function createTestOrder(
        string $status,
        int $totalMinor,
        int $shippingMinor,
        int $affiliateProfitMinor,
        int $wholesaleMinor,
        int $taxMinor,
        string $date,
        ?string $reasonCode = null
    ): Order {
        $order = Order::create([
            'tenant_id' => $this->tenant->id,
            'affiliate_id' => $this->affiliate->id,
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
                'affiliate_id' => $this->affiliate->id,
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
            'first_name' => 'محمد',
            'last_name' => 'أحمد',
            'line_one' => 'شارع التحرير',
            'city' => 'القاهرة',
            'state' => 'القاهرة',
            'contact_phone' => '01011223344',
            'created_at' => Carbon::parse($date)->midDay(),
        ]);

        OrderLine::create([
            'order_id' => $order->id,
            'purchasable_type' => ProductVariant::class,
            'purchasable_id' => $this->variant->id,
            'type' => 'physical',
            'identifier' => 'EAR-001',
            'description' => 'سماعات بلوتوث ممتازة',
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
