<?php

declare(strict_types=1);

namespace Tests\Feature\Reports;

use App\Domain\Affiliate\Settings\AffiliateProgramSettings;
use App\Domain\Orders\Enums\OrderStatus;
use App\Domain\Orders\Services\OrderTransitionService;
use App\Domain\Reports\Services\StorePublicTrustMetricsService;
use App\Models\Affiliate;
use App\Models\PayoutRequest;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy\Permissions\TenantPermissionMatrix;
use Carbon\Carbon;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

class PublicTrustMetricsTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected User $owner;

    protected Affiliate $affiliate;

    protected Currency $currency;

    protected Channel $channel;

    protected Country $country;

    protected ProductVariant $variant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PlanSeeder::class);

        $this->tenant = Tenant::create([
            'id' => 'trust-badges-store',
            'name' => 'Trust Badges Store',
            'status' => 'active',
        ]);
        $this->tenant->domains()->create(['domain' => 'trust-badges-store.localhost']);
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
            'url' => 'http://trust-badges-store.localhost',
        ]);

        $this->owner = User::create([
            'name' => 'Store Merchant',
            'email' => 'owner@trust-store.com',
            'password' => bcrypt('password123'),
            'tenant_id' => $this->tenant->id,
        ]);

        TenantPermissionMatrix::seedForTenant($this->tenant->id);
        TenantPermissionMatrix::assignRole($this->owner, TenantPermissionMatrix::ROLE_OWNER, $this->tenant->id);

        $this->tenant->run(function () {
            $this->affiliate = Affiliate::create([
                'tenant_id' => $this->tenant->id,
                'name' => 'محمود طارق',
                'email' => 'mahmoud@affiliate.test',
                'phone' => '01055556666',
                'password' => bcrypt('password'),
                'code' => 'MAH55',
                'status' => 'active',
                'kyc_status' => 'approved',
            ]);

            $productType = ProductType::firstOrCreate(['name' => 'Standard']);
            $taxClass = TaxClass::firstOrCreate(['name' => 'Standard']);

            $product = Product::create([
                'product_type_id' => $productType->id,
                'attribute_data' => [
                    'name' => new TranslatedText(collect(['ar' => 'منتج موثوق'])),
                ],
                'status' => 'published',
            ]);

            $this->variant = ProductVariant::create([
                'product_id' => $product->id,
                'tax_class_id' => $taxClass->id,
                'sku' => 'TRUST-01',
                'stock' => 25,
            ]);
        });
    }

    public function test_public_trust_metrics_calculation(): void
    {
        OrderTransitionService::$isTransitioning = true;

        $this->tenant->run(function () {
            // 4 Shipped total: 3 Delivered, 1 Returned -> Delivery Success Rate = 75.0%
            $this->createTestOrder(OrderStatus::DELIVERED->value);
            $this->createTestOrder(OrderStatus::DELIVERED->value);
            $this->createTestOrder(OrderStatus::DELIVERED->value);
            $this->createTestOrder(OrderStatus::RETURNED->value);

            // Paid Payout Request: paid 2 hours after creation
            PayoutRequest::create([
                'tenant_id' => $this->tenant->id,
                'affiliate_id' => $this->affiliate->id,
                'payment_method' => 'vodafone_cash',
                'amount_minor' => 150000,
                'fee_minor' => 0,
                'net_amount_minor' => 150000,
                'status' => PayoutRequest::STATUS_PAID,
                'created_at' => Carbon::now()->subHours(2),
                'paid_at' => Carbon::now(),
            ]);
        });

        OrderTransitionService::$isTransitioning = false;

        $metrics = $this->tenant->run(function () {
            /** @var StorePublicTrustMetricsService $service */
            $service = app(StorePublicTrustMetricsService::class);

            return $service->getMetrics();
        });

        $this->assertTrue($metrics['enabled']);
        $this->assertSame(75.0, $metrics['delivery_success_rate']);
        $this->assertSame(3, $metrics['delivered_orders_count']);
        $this->assertSame(1, $metrics['active_affiliates_count']);
        $this->assertSame('حوالي 2 ساعة', $metrics['average_payout_time']);
    }

    public function test_merchant_can_toggle_public_trust_metrics_visibility(): void
    {
        // 1. Initial state: enabled by default
        $this->tenant->run(function () {
            $settings = AffiliateProgramSettings::load();
            $this->assertTrue($settings->public_trust_metrics_enabled);
        });

        // 2. Merchant updates settings to disable trust badges
        $updateResponse = $this->actingAs($this->owner, 'web')
            ->post("https://{$this->tenant->id}.localhost/store/affiliates/settings", [
                'commission_model' => 'markup',
                'default_commission_rate' => 10,
                'min_commission_minor' => 1000,
                'max_commission_minor' => 100000,
                'min_selling_markup_minor' => 2000,
                'max_selling_markup_minor' => 200000,
                'min_payout_minor' => 10000,
                'maker_checker_threshold_minor' => 50000,
                'return_window_days' => 14,
                'payout_days' => ['sunday', 'wednesday'],
                'default_daily_order_limit' => 50,
                'release_mode' => 'later_of_both',
                'public_trust_metrics_enabled' => 0,
            ]);

        $updateResponse->assertRedirect();

        // 3. Verify setting persisted as false
        $this->tenant->run(function () {
            $settings = AffiliateProgramSettings::load();
            $this->assertFalse($settings->public_trust_metrics_enabled);

            /** @var StorePublicTrustMetricsService $service */
            $service = app(StorePublicTrustMetricsService::class);
            $metrics = $service->getMetrics();
            $this->assertFalse($metrics['enabled']);
        });
    }

    public function test_trust_badges_rendered_on_affiliate_catalog(): void
    {
        $response = $this->actingAs($this->affiliate, 'affiliate')
            ->get("https://{$this->tenant->id}.localhost/partners/catalog");

        $response->assertOk();
        $response->assertSee('مؤشرات الشفافية والأداء للمسوقين');
        $response->assertSee('سرعة سحب الأرباح');
        $response->assertSee('نسبة نجاح التوصيل');
    }

    protected function createTestOrder(string $status): Order
    {
        $order = Order::create([
            'tenant_id' => $this->tenant->id,
            'affiliate_id' => $this->affiliate->id,
            'channel_id' => $this->channel->id,
            'currency_code' => $this->currency->code,
            'status' => $status,
            'reference' => 'TRUST-'.bin2hex(random_bytes(4)),
            'customer_reference' => 'CUST-'.bin2hex(random_bytes(3)),
            'sub_total' => 20000,
            'total' => 25000,
            'shipping_total' => 5000,
            'discount_total' => 0,
            'tax_total' => 0,
            'tax_breakdown' => new TaxBreakdown,
            'affiliate_profit_minor' => 3000,
            'placed_at' => now(),
        ]);

        OrderAddress::create([
            'order_id' => $order->id,
            'type' => 'shipping',
            'country_id' => $this->country->id,
            'first_name' => 'عميل',
            'last_name' => 'تجريبي',
            'line_one' => 'شارع الثقة',
            'city' => 'القاهرة',
            'contact_phone' => '01011223344',
        ]);

        OrderLine::create([
            'order_id' => $order->id,
            'purchasable_type' => ProductVariant::class,
            'purchasable_id' => $this->variant->id,
            'type' => 'physical',
            'identifier' => $this->variant->sku,
            'description' => 'منتج موثوق',
            'unit_quantity' => 1,
            'quantity' => 1,
            'unit_price' => 20000,
            'sub_total' => 20000,
            'discount_total' => 0,
            'tax_breakdown' => new TaxBreakdown,
            'tax_total' => 0,
            'total' => 25000,
        ]);

        return $order;
    }
}
