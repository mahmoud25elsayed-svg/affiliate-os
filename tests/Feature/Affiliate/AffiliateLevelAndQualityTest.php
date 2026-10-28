<?php

declare(strict_types=1);

namespace Tests\Feature\Affiliate;

use App\Domain\Affiliate\Services\AffiliateLevelManager;
use App\Domain\Affiliate\Services\QualityScoreCalculator;
use App\Domain\Affiliate\Settings\AffiliateProgramSettings;
use App\Domain\Finance\Services\AccountResolver;
use App\Domain\Finance\Services\CommissionSettlementService;
use App\Domain\Finance\Services\Ledger;
use App\Domain\Orders\Enums\OrderStatus;
use App\Domain\Orders\Events\OrderDelivered;
use App\Models\Account;
use App\Models\Affiliate;
use App\Models\AffiliateLevel;
use App\Models\AffiliateQualityLog;
use App\Models\AffiliateReferralReward;
use App\Models\OrderEvent;
use App\Models\PayoutRequest;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WholesalePrice;
use App\Support\Location\City;
use App\Support\Location\Governorate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Lunar\Base\ValueObjects\Cart\TaxBreakdown;
use Lunar\FieldTypes\Text;
use Lunar\Models\Channel;
use Lunar\Models\Country;
use Lunar\Models\Currency;
use Lunar\Models\CustomerGroup;
use Lunar\Models\Language;
use Lunar\Models\Order;
use Lunar\Models\OrderAddress;
use Lunar\Models\Price;
use Lunar\Models\Product;
use Lunar\Models\ProductType;
use Lunar\Models\ProductVariant;
use Lunar\Models\TaxClass;
use Tests\TestCase;

class AffiliateLevelAndQualityTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected string $domain;

    protected Affiliate $affiliate;

    protected Channel $channel;

    protected Currency $currency;

    protected Language $language;

    protected ProductVariant $variant;

    protected TaxClass $taxClass;

    protected CustomerGroup $customerGroup;

    protected Governorate $governorate;

    protected City $city;

    protected Country $country;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'id' => 'test-tenant-levels',
            'name' => 'Levels Test Store',
        ]);
        $this->domain = 'test-tenant-levels.localhost';
        $this->tenant->domains()->create([
            'domain' => $this->domain,
        ]);
        tenancy()->initialize($this->tenant);

        // Seed Default Levels
        AffiliateLevel::seedDefaultsForTenant($this->tenant->id);

        $bronze = AffiliateLevel::query()->where('tenant_id', $this->tenant->id)->where('slug', AffiliateLevel::SLUG_BRONZE)->firstOrFail();

        $this->affiliate = Affiliate::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'مسوق تجريبي للمستويات',
            'code' => 'AFFLEVELS01',
            'email' => 'levels@example.com',
            'phone' => '01012345678',
            'password' => bcrypt('Password123!'),
            'status' => Affiliate::STATUS_ACTIVE,
            'commission_rate' => 10,
            'kyc_status' => Affiliate::KYC_APPROVED,
            'phone_verified_at' => Carbon::now(),
            'affiliate_level_id' => $bronze->id,
            'quality_score' => 100,
            'daily_order_limit' => 20,
            'total_delivered_orders' => 0,
            'delivery_rate_basis_points' => 10000,
        ]);

        $this->channel = Channel::firstOrCreate(
            ['handle' => 'storefront'],
            ['name' => 'Storefront', 'default' => true, 'url' => 'http://localhost']
        );

        $this->currency = Currency::firstOrCreate(
            ['code' => 'EGP'],
            ['name' => 'Egyptian Pound', 'exchange_rate' => 1.0, 'decimal_places' => 2, 'enabled' => true, 'default' => true]
        );

        $this->language = Language::firstOrCreate(
            ['code' => 'ar'],
            ['name' => 'Arabic', 'default' => true]
        );

        $this->customerGroup = CustomerGroup::firstOrCreate(
            ['handle' => 'retail'],
            ['name' => 'Retail Customers', 'default' => true]
        );

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

        $this->governorate = Governorate::create([
            'code' => 'CAI_LVL',
            'name_ar' => 'القاهرة',
            'name_en' => 'Cairo',
            'shipping_cost_minor' => 4500,
            'is_active' => true,
        ]);

        $this->city = City::create([
            'governorate_id' => $this->governorate->id,
            'name_ar' => 'مدينة نصر',
            'name_en' => 'Nasr City',
            'is_active' => true,
        ]);

        $this->taxClass = TaxClass::firstOrCreate(
            ['name' => 'Standard Tax'],
            ['default' => true]
        );

        $productType = ProductType::firstOrCreate(['name' => 'Standard']);
        $product = Product::create([
            'product_type_id' => $productType->id,
            'status' => 'published',
            'attribute_data' => [
                'name' => new Text('منتج اختبار المستويات'),
            ],
        ]);

        $this->variant = ProductVariant::create([
            'product_id' => $product->id,
            'tax_class_id' => $this->taxClass->id,
            'sku' => 'SKU-LVL-001',
            'stock' => 100,
            'purchasable' => 'always',
            'unit_quantity' => 1,
        ]);

        Price::create([
            'priceable_type' => ProductVariant::class,
            'priceable_id' => $this->variant->id,
            'currency_id' => $this->currency->id,
            'price' => 20000, // 200.00 EGP
            'min_quantity' => 1,
        ]);

        WholesalePrice::create([
            'product_variant_id' => $this->variant->id,
            'wholesale_price_minor' => 12000, // 120.00 EGP
            'min_price_minor' => 14000,
            'max_price_minor' => 30000,
            'is_active' => true,
        ]);
    }

    protected function tearDown(): void
    {
        tenancy()->end();
        parent::tearDown();
    }

    private function createOrderForAffiliate(Affiliate $affiliate, string $status = 'delivered', int $profitMinor = 5000): Order
    {
        $order = Order::create([
            'channel_id' => $this->channel->id,
            'status' => $status,
            'reference' => 'ORD-LVL-'.uniqid(),
            'sub_total' => 20000,
            'discount_total' => 0,
            'shipping_total' => 4500,
            'tax_breakdown' => new TaxBreakdown,
            'tax_total' => 0,
            'total' => 24500,
            'currency_code' => 'EGP',
            'compare_currency_code' => 'EGP',
            'exchange_rate' => 1.0,
            'placed_at' => Carbon::now(),
            'affiliate_id' => $affiliate->id,
            'price_snapshot' => json_encode([
                'affiliate_profit_minor' => $profitMinor,
                'customer_total_minor' => 24500,
                'selling_price_minor' => 20000,
                'wholesale_price_minor' => 12000,
                'shipping_cost_minor' => 4500,
                'cod_fee_minor' => 0,
            ]),
        ]);

        OrderAddress::create([
            'order_id' => $order->id,
            'type' => 'shipping',
            'first_name' => 'عميل تجريبي',
            'last_name' => 'المستويات',
            'line_one' => '10 شارع التحرير',
            'city' => 'القاهرة',
            'contact_phone' => '01099887766',
            'country_id' => $this->country->id,
        ]);

        return $order;
    }

    public function test_default_levels_are_seeded_with_correct_thresholds_and_bonuses(): void
    {
        $levels = AffiliateLevel::query()
            ->where('tenant_id', $this->tenant->id)
            ->orderBy('tier_order')
            ->get();

        $this->assertCount(4, $levels);

        $bronze = $levels[0];
        $this->assertEquals(AffiliateLevel::SLUG_BRONZE, $bronze->slug);
        $this->assertEquals(0, $bronze->min_delivered_orders);
        $this->assertEquals(0, $bronze->bonus_per_order_minor);
        $this->assertEquals(20, $bronze->daily_order_limit);

        $silver = $levels[1];
        $this->assertEquals(AffiliateLevel::SLUG_SILVER, $silver->slug);
        $this->assertEquals(20, $silver->min_delivered_orders);
        $this->assertEquals(7000, $silver->min_delivery_rate); // 70.00%
        $this->assertEquals(500, $silver->bonus_per_order_minor); // 5.00 EGP
        $this->assertEquals(50, $silver->daily_order_limit);

        $gold = $levels[2];
        $this->assertEquals(AffiliateLevel::SLUG_GOLD, $gold->slug);
        $this->assertEquals(100, $gold->min_delivered_orders);
        $this->assertEquals(8000, $gold->min_delivery_rate);
        $this->assertEquals(1500, $gold->bonus_per_order_minor); // 15.00 EGP
        $this->assertEquals(150, $gold->daily_order_limit);

        $platinum = $levels[3];
        $this->assertEquals(AffiliateLevel::SLUG_PLATINUM, $platinum->slug);
        $this->assertEquals(300, $platinum->min_delivered_orders);
        $this->assertEquals(8800, $platinum->min_delivery_rate);
        $this->assertEquals(3000, $platinum->bonus_per_order_minor); // 30.00 EGP
        $this->assertEquals(500, $platinum->daily_order_limit);
    }

    public function test_affiliate_level_evaluation_promotes_when_criteria_met(): void
    {
        $manager = app(AffiliateLevelManager::class);

        // Initially bronze
        $level = $manager->evaluateLevel($this->affiliate);
        $this->assertEquals(AffiliateLevel::SLUG_BRONZE, $level->slug);

        // Simulate reaching Silver requirements: 25 orders, 80% delivery rate, 80 quality score
        $this->affiliate->total_delivered_orders = 25;
        $this->affiliate->delivery_rate_basis_points = 8000;
        $this->affiliate->quality_score = 80;
        $this->affiliate->save();

        $silverLevel = $manager->evaluateLevel($this->affiliate);
        $this->assertEquals(AffiliateLevel::SLUG_SILVER, $silverLevel->slug);
        $this->assertEquals($silverLevel->id, $this->affiliate->fresh()->affiliate_level_id);

        // Simulate reaching Gold requirements: 110 orders, 85% rate, 85 score
        $this->affiliate->total_delivered_orders = 110;
        $this->affiliate->delivery_rate_basis_points = 8500;
        $this->affiliate->quality_score = 85;
        $this->affiliate->save();

        $goldLevel = $manager->evaluateLevel($this->affiliate);
        $this->assertEquals(AffiliateLevel::SLUG_GOLD, $goldLevel->slug);
    }

    public function test_quality_score_calculation_and_daily_limit_throttling(): void
    {
        $calculator = app(QualityScoreCalculator::class);

        // 1. New affiliate (< 5 orders) receives 100 score in grace period
        $stats = $calculator->calculate($this->affiliate);
        $this->assertEquals(100, $stats['quality_score']);
        $this->assertEquals(20, $stats['daily_order_limit']);

        // 2. Affiliate has 10 delivered orders and 0 returns => high score
        for ($i = 0; $i < 10; $i++) {
            $this->createOrderForAffiliate($this->affiliate, OrderStatus::DELIVERED->value);
        }

        $stats = $calculator->calculate($this->affiliate);
        $this->assertGreaterThanOrEqual(85, $stats['quality_score']);
        $this->assertEquals(10000, $stats['delivery_rate_basis_points']);

        // 3. Affiliate experiences heavy returns (10 returns vs 10 delivered = 50% delivery rate)
        for ($i = 0; $i < 15; $i++) {
            $this->createOrderForAffiliate($this->affiliate, OrderStatus::RETURNED->value);
        }

        $degradedStats = $calculator->calculate($this->affiliate);
        // Delivery rate = 10 / 25 = 40% => base score drops significantly
        $this->assertLessThan(60, $degradedStats['quality_score']);
    }

    public function test_daily_artisan_command_records_logs_and_updates_affiliates_with_timetravel(): void
    {
        // Place some orders
        for ($i = 0; $i < 6; $i++) {
            $this->createOrderForAffiliate($this->affiliate, OrderStatus::DELIVERED->value);
        }

        // Travel to tomorrow midnight
        $tomorrow = Carbon::now()->addDay();
        $this->travelTo($tomorrow);

        $this->artisan('affiliate:calculate-daily-quality-scores', [
            '--date' => $tomorrow->toDateString(),
        ])->assertSuccessful();

        // Assert log was persisted
        $this->assertDatabaseHas('affiliate_quality_logs', [
            'tenant_id' => $this->tenant->id,
            'affiliate_id' => $this->affiliate->id,
        ]);
        $this->assertEquals(
            $tomorrow->toDateString(),
            AffiliateQualityLog::where('affiliate_id', $this->affiliate->id)->firstOrFail()->calculated_date->toDateString()
        );

        $this->travelBack();
    }

    public function test_tier_bonus_is_posted_via_ledger_when_order_delivered(): void
    {
        // Promote affiliate to Silver (5.00 EGP = 500 minor bonus per delivered order)
        $silver = AffiliateLevel::query()
            ->where('tenant_id', $this->tenant->id)
            ->where('slug', AffiliateLevel::SLUG_SILVER)
            ->firstOrFail();

        $this->affiliate->update([
            'affiliate_level_id' => $silver->id,
            'daily_order_limit' => $silver->daily_order_limit,
        ]);

        $order = $this->createOrderForAffiliate($this->affiliate, OrderStatus::DELIVERED->value, 5000);

        // Fire OrderDelivered event
        $orderEvent = OrderEvent::create([
            'order_id' => $order->id,
            'from_status' => OrderStatus::SHIPPED->value,
            'to_status' => OrderStatus::DELIVERED->value,
            'actor_type' => User::class,
            'actor_id' => 1,
            'reason' => 'تم التسليم بنجاح للعميل',
        ]);

        event(new OrderDelivered($order, $orderEvent));

        // Assert regular commission was posted: 5000 minor
        $pendingAccount = app(AccountResolver::class)->getAffiliatePendingAccount($this->affiliate);
        $bonusExpenseAccount = app(AccountResolver::class)->getStoreBonusExpenseAccount();

        // Total pending balance should be regular commission (5000) + tier bonus (500) = 5500 minor
        $this->assertEquals(5500, $pendingAccount->fresh()->balance_minor);
        $this->assertEquals(-500, $bonusExpenseAccount->fresh()->balance_minor);

        // Check tier bonus journal entry exists with idempotency key
        $this->assertDatabaseHas('journal_entries', [
            'idempotency_key' => "order_tier_bonus_{$order->id}_{$this->affiliate->id}",
            'type' => 'tier_bonus',
        ]);
    }

    public function test_affiliate_to_affiliate_referral_reward_is_awarded_via_ledger(): void
    {
        // Create Referrer affiliate A
        $referrer = Affiliate::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'المسوق القديم المحيل',
            'code' => 'REFERRER01',
            'email' => 'referrer@example.com',
            'phone' => '01000000001',
            'password' => bcrypt('Password123!'),
            'status' => Affiliate::STATUS_ACTIVE,
            'commission_rate' => 10,
            'kyc_status' => Affiliate::KYC_APPROVED,
        ]);

        // Link current affiliate as referred by $referrer
        $this->affiliate->update([
            'referred_by_id' => $referrer->id,
        ]);

        $settings = AffiliateProgramSettings::load();
        $threshold = $settings->getReferralRewardThreshold(); // 5 orders
        $rewardAmount = $settings->getReferralRewardAmountMinor(); // 10000 minor = 100.00 EGP

        // Deliver 4 orders -> not yet threshold
        for ($i = 0; $i < 4; $i++) {
            $order = $this->createOrderForAffiliate($this->affiliate, OrderStatus::DELIVERED->value);
            $eventRecord = OrderEvent::create([
                'order_id' => $order->id,
                'from_status' => OrderStatus::SHIPPED->value,
                'to_status' => OrderStatus::DELIVERED->value,
                'actor_type' => User::class,
                'actor_id' => 1,
                'reason' => 'تسليم',
            ]);
            event(new OrderDelivered($order, $eventRecord));
        }

        $referrerPending = app(AccountResolver::class)->getAffiliatePendingAccount($referrer);
        $this->assertEquals(0, $referrerPending->fresh()->balance_minor);
        $this->assertDatabaseMissing('affiliate_referral_rewards', [
            'referrer_id' => $referrer->id,
            'referred_id' => $this->affiliate->id,
        ]);

        // Deliver 5th order -> hits threshold!
        $fifthOrder = $this->createOrderForAffiliate($this->affiliate, OrderStatus::DELIVERED->value);
        $fifthEvent = OrderEvent::create([
            'order_id' => $fifthOrder->id,
            'from_status' => OrderStatus::SHIPPED->value,
            'to_status' => OrderStatus::DELIVERED->value,
            'actor_type' => User::class,
            'actor_id' => 1,
            'reason' => 'تسليم الطلب الخامس',
        ]);
        event(new OrderDelivered($fifthOrder, $fifthEvent));

        // Referrer should now have +10000 minor in pending account
        $this->assertEquals($rewardAmount, $referrerPending->fresh()->balance_minor);

        $this->assertDatabaseHas('affiliate_referral_rewards', [
            'tenant_id' => $this->tenant->id,
            'referrer_id' => $referrer->id,
            'referred_id' => $this->affiliate->id,
            'threshold_orders' => $threshold,
            'reward_amount_minor' => $rewardAmount,
        ]);

        // Delivering a 6th order does NOT re-award the bonus (idempotency check)
        $sixthOrder = $this->createOrderForAffiliate($this->affiliate, OrderStatus::DELIVERED->value);
        $sixthEvent = OrderEvent::create([
            'order_id' => $sixthOrder->id,
            'from_status' => OrderStatus::SHIPPED->value,
            'to_status' => OrderStatus::DELIVERED->value,
            'actor_type' => User::class,
            'actor_id' => 1,
            'reason' => 'تسليم الطلب السادس',
        ]);
        event(new OrderDelivered($sixthOrder, $sixthEvent));

        $this->assertEquals($rewardAmount, $referrerPending->fresh()->balance_minor);
        $this->assertEquals(1, AffiliateReferralReward::where('referrer_id', $referrer->id)->count());
    }

    public function test_pwa_levels_screen_renders_with_inertia(): void
    {
        $response = $this->actingAs($this->affiliate, 'affiliate')
            ->get("https://{$this->domain}/partners/app/levels");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('App/Levels')
            ->has('currentLevel')
            ->has('allLevels')
            ->has('stats')
            ->has('referralProgram')
            ->where('currentLevel.name', 'برونزي')
            ->where('referralProgram.referral_code', $this->affiliate->code)
        );
    }

    public function test_stage_two_gateway_full_cycle_and_clean_ledger_reconcile(): void
    {
        /**
         * Gateway scenario:
         * 1. Affiliate places order.
         * 2. Order delivered -> commission posted to pending.
         * 3. Return window passes (`travelTo`).
         * 4. Hourly settlement job runs -> moves pending to available.
         * 5. Affiliate requests payout.
         * 6. Merchant approves and executes payout.
         * 7. Artisan command ledger:reconcile runs and reports zero discrepancy.
         */
        $resolver = app(AccountResolver::class);
        $profitMinor = 6000; // 60.00 EGP

        // 1 & 2. Order delivered
        $order = $this->createOrderForAffiliate($this->affiliate, OrderStatus::DELIVERED->value, $profitMinor);
        $orderEvent = OrderEvent::create([
            'order_id' => $order->id,
            'from_status' => OrderStatus::SHIPPED->value,
            'to_status' => OrderStatus::DELIVERED->value,
            'actor_type' => User::class,
            'actor_id' => 1,
            'reason' => 'تم التسليم',
        ]);
        event(new OrderDelivered($order, $orderEvent));

        $pendingAcc = $resolver->getAffiliatePendingAccount($this->affiliate);
        $availableAcc = $resolver->getAffiliateAvailableAccount($this->affiliate);
        $this->assertEquals($profitMinor, $pendingAcc->fresh()->balance_minor);
        $this->assertEquals(0, $availableAcc->fresh()->balance_minor);

        // 3. Return window passes (15 days)
        $this->travelTo(Carbon::now()->addDays(15));

        // 4. Run CommissionSettlementService
        app(CommissionSettlementService::class)->settleEligibleDeliveredOrders();

        $this->assertEquals(0, $pendingAcc->fresh()->balance_minor);
        $this->assertEquals($profitMinor, $availableAcc->fresh()->balance_minor);

        // 5. Affiliate requests payout for the 60.00 EGP (6000 minor)
        // Temporarily adjust min_payout_minor in settings to allow 60 EGP
        $settings = AffiliateProgramSettings::load();
        $settings->min_payout_minor = 5000;
        $settings->save();

        $this->affiliate->update([
            'payout_method' => Affiliate::PAYOUT_METHOD_WALLET,
            'payout_details' => ['phone' => '01012345678'],
        ]);

        $payoutResponse = $this->actingAs($this->affiliate, 'affiliate')
            ->post("https://{$this->domain}/partners/app/wallet/payout", [
                'amount_minor' => 6000,
            ]);
        $payoutResponse->assertRedirect("https://{$this->domain}/partners/app/wallet/main");

        $payoutRequest = PayoutRequest::query()
            ->where('affiliate_id', $this->affiliate->id)
            ->firstOrFail();

        $this->assertEquals(6000, $payoutRequest->amount_minor);
        $this->assertEquals(PayoutRequest::STATUS_REQUESTED, $payoutRequest->status);

        // Locked account holds the 6000 minor, available is 0
        $lockedAcc = $resolver->getAffiliateLockedAccount($this->affiliate);
        $this->assertEquals(6000, $lockedAcc->fresh()->balance_minor);
        $this->assertEquals(0, $availableAcc->fresh()->balance_minor);

        // 6. Merchant approves and executes (Maker-Checker with distinct users)
        $maker = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'معتمد السحب (Maker)',
            'email' => 'maker_payout@test.com',
            'password' => bcrypt('password'),
        ]);

        $checker = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'منفذ السحب (Checker)',
            'email' => 'checker_payout@test.com',
            'password' => bcrypt('password'),
        ]);

        $this->actingAs($maker)
            ->post("https://{$this->domain}/store/payouts/{$payoutRequest->id}/approve")
            ->assertRedirect();

        $this->actingAs($checker)
            ->post("https://{$this->domain}/store/payouts/{$payoutRequest->id}/execute", [
                'reference_number' => 'TXN-VODAFONE-998811',
            ])
            ->assertRedirect();

        $payoutRequest->refresh();
        $this->assertEquals(PayoutRequest::STATUS_PAID, $payoutRequest->status);
        $this->assertEquals(0, $lockedAcc->fresh()->balance_minor);

        // 7. Run ledger:reconcile command
        $this->artisan('ledger:reconcile')->assertSuccessful();

        // Assert double-entry invariant: sum of all account balances in system = 0
        $sumAllBalances = Account::sum('balance_minor');
        $this->assertEquals(0, $sumAllBalances);

        $this->travelBack();
    }
}
