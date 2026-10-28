<?php

declare(strict_types=1);

namespace Tests\Feature\Customer;

use App\Domain\Customer\Contracts\CustomerWalletServiceInterface;
use App\Domain\Customer\Services\CustomerLoyaltyService;
use App\Domain\Customer\Services\WalletPaymentService;
use App\Domain\Orders\DTOs\OrderActor;
use App\Domain\Orders\Enums\OrderStatus;
use App\Domain\Orders\Services\OrderTransitionService;
use App\Models\CustomerWallet;
use App\Models\CustomerWalletTransaction;
use App\Models\JournalEntry;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Billing\Exceptions\FeatureNotAvailableException;
use Database\Seeders\PlanSeeder;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Lunar\Base\ValueObjects\Cart\TaxBreakdown;
use Lunar\FieldTypes\Text;
use Lunar\Models\Channel;
use Lunar\Models\Country;
use Lunar\Models\Currency;
use Lunar\Models\Customer as LunarCustomer;
use Lunar\Models\Language;
use Lunar\Models\Order;
use Lunar\Models\OrderAddress;
use Lunar\Models\OrderLine;
use Lunar\Models\Product;
use Lunar\Models\ProductType;
use Lunar\Models\ProductVariant;
use Lunar\Models\TaxClass;
use Tests\TestCase;

class CustomerWalletAndLoyaltyTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected User $staffUser;

    protected Channel $channel;

    protected Currency $currency;

    protected Country $country;

    protected LunarCustomer $customer;

    protected ProductVariant $variant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PlanSeeder::class);

        $this->tenant = Tenant::create([
            'id' => 'wallet-test-store',
            'name' => 'Wallet Test Store',
            'status' => 'active',
        ]);
        $this->tenant->domains()->create(['domain' => 'wallet-test-store.localhost']);
        tenancy()->initialize($this->tenant);

        // Assign Pro plan so loyalty feature is enabled
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
            'url' => 'http://wallet-test-store.localhost',
        ]);

        \App\Models\Warehouse::firstOrCreate([
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
            'name' => 'مسؤول المتجر',
            'email' => 'store_manager@test.com',
            'password' => bcrypt('password'),
        ]);

        $this->customer = LunarCustomer::create([
            'first_name' => 'سارة',
            'last_name' => 'المهدي',
            'title' => 'Ms',
        ]);

        // Product setup
        $taxClass = TaxClass::create(['name' => 'Default Tax']);
        $productType = ProductType::create(['name' => 'Standard Product']);
        $product = Product::create([
            'product_type_id' => $productType->id,
            'status' => 'published',
            'attribute_data' => ['name' => new Text('حقيبة يد جلدية فاخرة')],
        ]);

        $this->variant = ProductVariant::create([
            'product_id' => $product->id,
            'tax_class_id' => $taxClass->id,
            'sku' => 'BAG-01',
            'unit_quantity' => 1,
        ]);
    }

    protected function createOrder(int $totalMinor = 50000): Order
    {
        OrderTransitionService::$isTransitioning = true;

        try {
            /** @var Order $order */
            $order = Order::create([
                'tenant_id' => $this->tenant->id,
                'channel_id' => $this->channel->id,
                'customer_id' => $this->customer->id,
                'currency_code' => $this->currency->code,
                'status' => OrderStatus::CONFIRMED->value,
                'reference' => 'WAL-'.bin2hex(random_bytes(4)),
                'customer_reference' => 'CUST-'.bin2hex(random_bytes(3)),
                'sub_total' => $totalMinor - 4500,
                'total' => $totalMinor,
                'shipping_total' => 4500,
                'discount_total' => 0,
                'tax_total' => 0,
                'tax_breakdown' => new TaxBreakdown,
                'placed_at' => now(),
                'price_snapshot' => json_encode([
                    'quantity' => 1,
                    'customer_total_minor' => $totalMinor,
                ]),
                'notes' => 'طلب لاختبار المحفظة والولاء',
            ]);

            OrderAddress::create([
                'order_id' => $order->id,
                'type' => 'shipping',
                'country_id' => $this->country->id,
                'first_name' => 'سارة',
                'last_name' => 'المهدي',
                'line_one' => 'شارع الثورة - مصر الجديدة',
                'city' => 'القاهرة',
                'contact_phone' => '01055667788',
            ]);

            OrderLine::create([
                'order_id' => $order->id,
                'purchasable_type' => ProductVariant::class,
                'purchasable_id' => $this->variant->id,
                'type' => 'physical',
                'identifier' => 'BAG-01',
                'unit_quantity' => 1,
                'quantity' => 1,
                'unit_price' => $totalMinor - 4500,
                'sub_total' => $totalMinor - 4500,
                'discount_total' => 0,
                'tax_breakdown' => new TaxBreakdown,
                'tax_total' => 0,
                'total' => $totalMinor - 4500,
                'description' => 'حقيبة يد جلدية فاخرة',
            ]);

            return $order;
        } finally {
            OrderTransitionService::$isTransitioning = false;
        }
    }

    public function test_customer_wallet_credits_and_debits_atomically(): void
    {
        /** @var CustomerWalletServiceInterface $walletService */
        $walletService = app(CustomerWalletServiceInterface::class);

        $wallet = $walletService->getWallet($this->customer);
        $this->assertInstanceOf(CustomerWallet::class, $wallet);
        $this->assertSame(0, $wallet->balance_minor);

        // 1. Credit 500 EGP (50,000 minor)
        $txCredit = $walletService->credit(
            customer: $this->customer,
            amountMinor: 50000,
            type: CustomerWalletTransaction::TYPE_DEPOSIT,
            referenceId: 'DEP-001',
            notes: 'إيداع رصيد بالمحفظة'
        );

        $this->assertSame(50000, $txCredit->amount_minor);
        $this->assertSame(50000, $walletService->getBalance($this->customer));

        // 2. Debit 200 EGP (20,000 minor)
        $txDebit = $walletService->debit(
            customer: $this->customer,
            amountMinor: 20000,
            type: CustomerWalletTransaction::TYPE_PURCHASE,
            referenceId: 'PURCHASE-001',
            notes: 'سداد مشتريات'
        );

        $this->assertSame(-20000, $txDebit->amount_minor);
        $this->assertSame(30000, $walletService->getBalance($this->customer));

        // 3. Attempt to debit more than available (40,000 minor > 30,000 minor)
        $this->expectException(DomainException::class);
        $walletService->debit(
            customer: $this->customer,
            amountMinor: 40000,
            type: CustomerWalletTransaction::TYPE_PURCHASE
        );
    }

    public function test_customer_earns_loyalty_points_on_delivered_order_with_financial_ledger(): void
    {
        /** @var CustomerLoyaltyService $loyaltyService */
        $loyaltyService = app(CustomerLoyaltyService::class);
        /** @var OrderTransitionService $transitionService */
        $transitionService = app(OrderTransitionService::class);

        $order = $this->createOrder(50000); // 500 EGP

        // Transition order: CONFIRMED -> RESERVED -> PACKED -> SHIPPED -> OUT_FOR_DELIVERY -> DELIVERED
        $actor = OrderActor::system();
        $transitionService->transition($order, OrderStatus::RESERVED, null, $actor);
        $transitionService->transition($order, OrderStatus::PACKED, null, $actor);
        $transitionService->transition($order, OrderStatus::SHIPPED, null, $actor);
        $transitionService->transition($order, OrderStatus::OUT_FOR_DELIVERY, null, $actor);
        $transitionService->transition($order, OrderStatus::DELIVERED, null, $actor);

        // 50,000 minor / 1,000 minor per point = 50 points
        $points = $loyaltyService->getPointsBalance($this->customer);
        $this->assertSame(50, $points);

        // Assert financial ledger journal entry posted
        /** @var JournalEntry|null $entry */
        $entry = JournalEntry::query()
            ->where('idempotency_key', "loyalty_points_order_{$order->id}")
            ->with('lines')
            ->first();

        $this->assertNotNull($entry);
        $this->assertSame('loyalty_points_earned', $entry->type);
        $this->assertSame(0, (int) $entry->lines->sum('amount_minor'));

        // Assert idempotency: calling awardPointsForOrder again does not award extra points
        $secondAttempt = $loyaltyService->awardPointsForOrder($order);
        $this->assertNull($secondAttempt);
        $this->assertSame(50, $loyaltyService->getPointsBalance($this->customer));
    }

    public function test_customer_can_redeem_loyalty_points_to_wallet_credit(): void
    {
        /** @var CustomerLoyaltyService $loyaltyService */
        $loyaltyService = app(CustomerLoyaltyService::class);
        /** @var CustomerWalletServiceInterface $walletService */
        $walletService = app(CustomerWalletServiceInterface::class);

        $account = $loyaltyService->getAccount($this->customer);
        $account->award(200, 'test_initial_points');

        $this->assertSame(200, $loyaltyService->getPointsBalance($this->customer));
        $this->assertSame(0, $walletService->getBalance($this->customer));

        // Redeem 100 points -> 100 * 10 = 1,000 minor units (10 EGP credit)
        $result = $loyaltyService->redeemPointsToWallet($this->customer, 100);

        $this->assertSame(100, $loyaltyService->getPointsBalance($this->customer));
        $this->assertSame(1000, $walletService->getBalance($this->customer));

        $this->assertSame(-100, $result['transaction']->points);
        $this->assertSame(1000, $result['wallet_transaction']->amount_minor);

        // Verify balanced ledger entry
        /** @var JournalEntry|null $entry */
        $entry = JournalEntry::query()
            ->where('idempotency_key', "loyalty_redeem_{$result['transaction']->id}")
            ->with('lines')
            ->first();

        $this->assertNotNull($entry);
        $this->assertSame(0, (int) $entry->lines->sum('amount_minor'));
    }

    public function test_wallet_payment_service_deducts_order_total_and_posts_revenue_ledger(): void
    {
        /** @var CustomerWalletServiceInterface $walletService */
        $walletService = app(CustomerWalletServiceInterface::class);
        /** @var WalletPaymentService $paymentService */
        $paymentService = app(WalletPaymentService::class);

        // Top up customer wallet with 300 EGP (30,000 minor)
        $walletService->credit($this->customer, 30000, CustomerWalletTransaction::TYPE_DEPOSIT);

        $order = $this->createOrder(25000); // 250 EGP order

        $tx = $paymentService->applyWalletBalance($order, $this->customer, 25000);

        $this->assertSame(-25000, $tx->amount_minor);
        $this->assertSame(5000, $walletService->getBalance($this->customer)); // 30,000 - 25,000 = 5,000

        $order->refresh();
        $snapshot = json_decode((string) $order->price_snapshot, true);
        $this->assertSame(25000, $snapshot['wallet_paid_minor'] ?? null);
        $this->assertSame(0, $snapshot['remaining_due_minor'] ?? null);

        // Assert balanced ledger entry
        /** @var JournalEntry|null $entry */
        $entry = JournalEntry::query()
            ->where('idempotency_key', "wallet_payment_order_{$order->id}_{$tx->id}")
            ->with('lines')
            ->first();

        $this->assertNotNull($entry);
        $this->assertSame('wallet_purchase_payment', $entry->type);
        $this->assertSame(0, (int) $entry->lines->sum('amount_minor'));
    }

    public function test_merchant_can_view_wallets_and_perform_manual_adjustments(): void
    {
        /** @var CustomerWalletServiceInterface $walletService */
        $walletService = app(CustomerWalletServiceInterface::class);
        $wallet = $walletService->getWallet($this->customer);

        // 1. Index View
        $indexResponse = $this->actingAs($this->staffUser)
            ->get('https://wallet-test-store.localhost/store/wallets');

        $indexResponse->assertOk();
        $indexResponse->assertViewIs('store.wallets.index');
        $indexResponse->assertSee('سارة المهدي');

        // 2. Show View
        $showResponse = $this->actingAs($this->staffUser)
            ->get("https://wallet-test-store.localhost/store/wallets/{$wallet->id}");

        $showResponse->assertOk();
        $showResponse->assertViewIs('store.wallets.show');
        $showResponse->assertSee('سارة المهدي');

        // 3. Manual Wallet Adjustment (Credit 150 EGP)
        $walletAdjResponse = $this->actingAs($this->staffUser)
            ->post("https://wallet-test-store.localhost/store/wallets/{$wallet->id}/adjust-wallet", [
                'action' => 'credit',
                'amount' => '150.00',
                'notes' => 'هدية ترحيبية من إدارة المتجر',
            ]);

        $walletAdjResponse->assertRedirect();
        $this->assertSame(15000, $walletService->getBalance($this->customer));

        // 4. Manual Loyalty Adjustment (Award 50 points)
        $loyaltyAdjResponse = $this->actingAs($this->staffUser)
            ->post("https://wallet-test-store.localhost/store/wallets/{$wallet->id}/adjust-loyalty", [
                'action' => 'award',
                'points' => 50,
                'notes' => 'نقاط إضافية بمناسبة العيد',
            ]);

        $loyaltyAdjResponse->assertRedirect();
        /** @var CustomerLoyaltyService $loyaltyService */
        $loyaltyService = app(CustomerLoyaltyService::class);
        $this->assertSame(50, $loyaltyService->getPointsBalance($this->customer));
    }

    public function test_loyalty_feature_flag_is_enforced(): void
    {
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
            ->get('https://wallet-test-store.localhost/store/wallets');
    }
}
