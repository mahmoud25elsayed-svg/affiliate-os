<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Domain\Finance\Enums\CodRemittanceStatus;
use App\Domain\Finance\Enums\RemittanceItemMatchStatus;
use App\Domain\Finance\Services\AccountResolver;
use App\Domain\Finance\Services\CodRemittanceService;
use App\Domain\Finance\Services\CodSettlementService;
use App\Domain\Fulfillment\Enums\ShipmentStatus;
use App\Domain\Orders\Enums\OrderStatus;
use App\Domain\Orders\Services\OrderTransitionService;
use App\Models\Account;
use App\Models\Affiliate;
use App\Models\CodRemittance;
use App\Models\CourierAccount;
use App\Models\JournalEntry;
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

class CodReconciliationAndSettlementTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected User $staffUser;

    protected Channel $channel;

    protected Currency $currency;

    protected Country $country;

    protected Governorate $cairo;

    protected ProductVariant $variant;

    protected Affiliate $affiliate;

    protected CourierAccount $courierAccount;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PlanSeeder::class);

        $this->tenant = Tenant::create([
            'id' => 'cod-test-store',
            'name' => 'COD Reconciliation Store',
            'status' => 'active',
        ]);
        $this->tenant->domains()->create(['domain' => 'cod-test-store.localhost']);
        tenancy()->initialize($this->tenant);

        // Assign Pro plan so cod_reconciliation feature is enabled
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
            'url' => 'http://cod-test-store.localhost',
        ]);

        $this->cairo = Governorate::create([
            'code' => 'CAI',
            'name_ar' => 'القاهرة',
            'name_en' => 'Cairo',
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
            'name' => 'محاسب المتجر',
            'email' => 'accountant@test.com',
            'password' => bcrypt('password'),
        ]);

        $this->affiliate = Affiliate::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'مسوق الكود التجريبي',
            'code' => 'AFF-COD-01',
            'phone' => '+201099881122',
            'email' => 'affiliate_cod@test.com',
            'status' => 'active',
        ]);

        // Product setup
        $taxClass = TaxClass::create(['name' => 'Default Tax']);
        $productType = ProductType::create(['name' => 'Standard Product']);
        $product = Product::create([
            'product_type_id' => $productType->id,
            'status' => 'published',
            'attribute_data' => ['name' => new Text('سماعة بلوتوث لاسلكية')],
        ]);

        $this->variant = ProductVariant::create([
            'product_id' => $product->id,
            'tax_class_id' => $taxClass->id,
            'sku' => 'EARBUDS-01',
            'unit_quantity' => 1,
        ]);

        WholesalePrice::create([
            'tenant_id' => $this->tenant->id,
            'product_variant_id' => $this->variant->id,
            'wholesale_price_minor' => 15000, // 150 EGP
            'min_price_minor' => 20000,
            'max_price_minor' => 40000,
            'status' => WholesalePrice::STATUS_ACTIVE,
        ]);

        $this->courierAccount = CourierAccount::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Bosta Courier',
            'driver' => 'bosta',
            'is_active' => true,
            'is_default' => true,
        ]);
    }

    /**
     * @return array{0: Order, 1: Shipment}
     */
    protected function createDeliveredOrder(string $trackingNumber, int $codMinor = 39500, int $shippingFeeMinor = 4500): array
    {
        OrderTransitionService::$isTransitioning = true;

        try {
            /** @var Order $order */
            $order = Order::create([
                'tenant_id' => $this->tenant->id,
                'affiliate_id' => $this->affiliate->id,
                'channel_id' => $this->channel->id,
                'currency_code' => $this->currency->code,
                'status' => OrderStatus::DELIVERED->value,
                'reference' => 'COD-'.bin2hex(random_bytes(4)),
                'customer_reference' => 'CUST-'.bin2hex(random_bytes(3)),
                'sub_total' => $codMinor - $shippingFeeMinor,
                'total' => $codMinor,
                'shipping_total' => $shippingFeeMinor,
                'discount_total' => 0,
                'tax_total' => 0,
                'tax_breakdown' => new TaxBreakdown,
                'placed_at' => now(),
                'price_snapshot' => json_encode([
                    'affiliate_id' => $this->affiliate->id,
                    'variant_id' => $this->variant->id,
                    'quantity' => 1,
                    'selling_price_minor' => $codMinor - $shippingFeeMinor,
                    'selling_total_minor' => $codMinor - $shippingFeeMinor,
                    'wholesale_unit_minor' => 15000,
                    'wholesale_total_minor' => 15000,
                    'shipping_cost_minor' => $shippingFeeMinor,
                    'customer_total_minor' => $codMinor,
                    'affiliate_profit_minor' => ($codMinor - $shippingFeeMinor) - 15000,
                ]),
                'notes' => 'طلب مسلّم بانتظار التسوية',
            ]);

            OrderAddress::create([
                'order_id' => $order->id,
                'type' => 'shipping',
                'country_id' => $this->country->id,
                'first_name' => 'أحمد',
                'last_name' => 'خالد',
                'line_one' => 'شارع التحرير',
                'city' => 'القاهرة',
                'contact_phone' => '01011223344',
            ]);

            OrderLine::create([
                'order_id' => $order->id,
                'purchasable_type' => ProductVariant::class,
                'purchasable_id' => $this->variant->id,
                'type' => 'physical',
                'identifier' => 'EARBUDS-01',
                'unit_quantity' => 1,
                'quantity' => 1,
                'unit_price' => $codMinor - $shippingFeeMinor,
                'sub_total' => $codMinor - $shippingFeeMinor,
                'discount_total' => 0,
                'tax_breakdown' => new TaxBreakdown,
                'tax_total' => 0,
                'total' => $codMinor - $shippingFeeMinor,
                'description' => 'سماعة بلوتوث لاسلكية',
            ]);

            /** @var Shipment $shipment */
            $shipment = Shipment::create([
                'tenant_id' => $this->tenant->id,
                'order_id' => $order->id,
                'courier_account_id' => $this->courierAccount->id,
                'tracking_number' => $trackingNumber,
                'waybill_url' => "https://track.bosta.co/{$trackingNumber}",
                'status' => ShipmentStatus::DELIVERED,
                'cod_amount_minor' => $codMinor,
                'shipping_fees_minor' => $shippingFeeMinor,
                'recipient_name' => 'أحمد خالد',
                'recipient_phone' => '01011223344',
                'delivery_city' => 'القاهرة',
                'delivery_address' => 'شارع التحرير',
                'dispatched_at' => now()->subDays(2),
                'delivered_at' => now()->subDay(),
            ]);

            // Setup pending affiliate commission in ledger
            /** @var AccountResolver $resolver */
            $resolver = app(AccountResolver::class);
            $pendingAcc = $resolver->getAffiliatePendingAccount($this->affiliate);
            $pendingAcc->update(['balance_minor' => $pendingAcc->balance_minor + (($codMinor - $shippingFeeMinor) - 15000)]);

            return [$order, $shipment];
        } finally {
            OrderTransitionService::$isTransitioning = false;
        }
    }

    public function test_can_create_cod_remittance_and_match_shipments(): void
    {
        [$order1, $shipment1] = $this->createDeliveredOrder('BST-TRK-001', 39500, 4500);
        [$order2, $shipment2] = $this->createDeliveredOrder('BST-TRK-002', 45000, 5000);

        /** @var CodRemittanceService $remittanceService */
        $remittanceService = app(CodRemittanceService::class);

        $rows = [
            [
                'tracking_number' => 'BST-TRK-001',
                'reported_cod_minor' => 39500,
                'shipping_fee_minor' => 4500,
                'status' => 'delivered',
            ],
            [
                'tracking_number' => 'BST-TRK-002',
                'reported_cod_minor' => 45000,
                'shipping_fee_minor' => 5000,
                'status' => 'delivered',
            ],
        ];

        $remittance = $remittanceService->createRemittance(
            courierAccount: $this->courierAccount,
            rows: $rows,
            reference: 'REM-BOSTA-OCT-01',
            creator: $this->staffUser
        );

        $this->assertInstanceOf(CodRemittance::class, $remittance);
        $this->assertSame(CodRemittanceStatus::RECONCILED, $remittance->status);
        $this->assertSame(84500, $remittance->total_collected_minor); // 395 + 450
        $this->assertSame(9500, $remittance->total_shipping_fees_minor); // 45 + 50
        $this->assertSame(75000, $remittance->net_payout_minor); // 845 - 95
        $this->assertSame(2, $remittance->matched_orders_count);
        $this->assertSame(0, $remittance->discrepancy_orders_count);

        $this->assertDatabaseHas('cod_remittances', [
            'id' => $remittance->id,
            'reference' => 'REM-BOSTA-OCT-01',
            'status' => CodRemittanceStatus::RECONCILED->value,
        ]);

        $this->assertDatabaseHas('cod_remittance_items', [
            'cod_remittance_id' => $remittance->id,
            'tracking_number' => 'BST-TRK-001',
            'match_status' => RemittanceItemMatchStatus::MATCHED->value,
            'order_id' => $order1->id,
            'shipment_id' => $shipment1->id,
        ]);
    }

    public function test_detects_cod_amount_and_fee_discrepancies(): void
    {
        [$order1, $shipment1] = $this->createDeliveredOrder('BST-TRK-101', 39500, 4500);

        /** @var CodRemittanceService $remittanceService */
        $remittanceService = app(CodRemittanceService::class);

        $rows = [
            // Row 1: Amount mismatch (courier collected 300 EGP instead of 395 EGP)
            [
                'tracking_number' => 'BST-TRK-101',
                'reported_cod_minor' => 30000,
                'shipping_fee_minor' => 4500,
            ],
            // Row 2: Unknown tracking number (not found in database)
            [
                'tracking_number' => 'UNKNOWN-999',
                'reported_cod_minor' => 50000,
                'shipping_fee_minor' => 5000,
            ],
        ];

        $remittance = $remittanceService->createRemittance(
            courierAccount: $this->courierAccount,
            rows: $rows,
            reference: 'REM-DISCREPANT-01'
        );

        $this->assertSame(CodRemittanceStatus::DISCREPANT, $remittance->status);
        $this->assertSame(2, $remittance->discrepancy_orders_count);
        $this->assertSame(0, $remittance->matched_orders_count);

        // Discrepancy amounts: 9500 (39500 - 30000) + 50000 (unknown tracking) = 59500
        $this->assertSame(59500, $remittance->discrepancy_amount_minor);

        $this->assertDatabaseHas('cod_remittance_items', [
            'cod_remittance_id' => $remittance->id,
            'tracking_number' => 'BST-TRK-101',
            'match_status' => RemittanceItemMatchStatus::AMOUNT_MISMATCH->value,
            'cod_discrepancy_minor' => -9500,
        ]);

        $this->assertDatabaseHas('cod_remittance_items', [
            'cod_remittance_id' => $remittance->id,
            'tracking_number' => 'UNKNOWN-999',
            'match_status' => RemittanceItemMatchStatus::NOT_FOUND->value,
        ]);
    }

    public function test_settle_remittance_transitions_orders_releases_commission_and_posts_ledger(): void
    {
        [$order1, $shipment1] = $this->createDeliveredOrder('BST-TRK-201', 39500, 4500);
        [$order2, $shipment2] = $this->createDeliveredOrder('BST-TRK-202', 25000, 4000);

        /** @var CodRemittanceService $remittanceService */
        $remittanceService = app(CodRemittanceService::class);
        /** @var CodSettlementService $settlementService */
        $settlementService = app(CodSettlementService::class);
        /** @var AccountResolver $accountResolver */
        $accountResolver = app(AccountResolver::class);

        $rows = [
            ['tracking_number' => 'BST-TRK-201', 'reported_cod_minor' => 39500, 'shipping_fee_minor' => 4500],
            ['tracking_number' => 'BST-TRK-202', 'reported_cod_minor' => 25000, 'shipping_fee_minor' => 4000],
        ];

        $remittance = $remittanceService->createRemittance(
            courierAccount: $this->courierAccount,
            rows: $rows,
            reference: 'REM-SETTLE-TEST-01',
            creator: $this->staffUser
        );

        $this->assertSame(CodRemittanceStatus::RECONCILED, $remittance->status);

        // Execute settlement
        $settledRemittance = $settlementService->settleRemittance(
            remittance: $remittance,
            settler: $this->staffUser,
            bankReference: 'CIB-TRANSFER-998877',
            notes: 'تم استلام المبلغ بحساب الشركة بالبنك التجاري الدولي'
        );

        $this->assertSame(CodRemittanceStatus::SETTLED, $settledRemittance->status);
        $this->assertNotNull($settledRemittance->settled_at);
        $this->assertSame($this->staffUser->id, $settledRemittance->settled_by);
        $this->assertSame('CIB-TRANSFER-998877', $settledRemittance->bank_reference);

        // 1. Assert Orders transitioned to SETTLED
        $order1->refresh();
        $order2->refresh();
        $this->assertSame(OrderStatus::SETTLED->value, (string) $order1->status);
        $this->assertSame(OrderStatus::SETTLED->value, (string) $order2->status);

        // 2. Assert Affiliate commission moved to available
        $availableAccount = $accountResolver->getAffiliateAvailableAccount($this->affiliate);
        $this->assertGreaterThan(0, $availableAccount->balance_minor);

        // 3. Assert Financial Ledger Journal Entry was posted and balances exactly
        /** @var JournalEntry|null $entry */
        $entry = JournalEntry::query()
            ->where('idempotency_key', "cod_remittance_settled_{$remittance->id}")
            ->with('lines.account')
            ->first();

        $this->assertNotNull($entry);
        $this->assertSame('cod_remittance_settled', $entry->type);

        $lines = $entry->lines;
        $this->assertNotEmpty($lines);

        $sum = $lines->sum('amount_minor');
        $this->assertSame(0, (int) $sum, 'Ledger entry lines must balance to exactly 0 (Debits == Credits).');

        // Check Bank Account has positive debit of net payout
        $bankAccount = $accountResolver->getStoreBankAccount();
        $bankLine = $lines->firstWhere('account_id', $bankAccount->id);
        $this->assertNotNull($bankLine);
        $this->assertSame($remittance->net_payout_minor, (int) $bankLine->amount_minor);

        // Check Courier Shipping Expense Account has positive debit
        $shippingExpenseAccount = $accountResolver->getStoreShippingExpenseAccount();
        $expenseLine = $lines->firstWhere('account_id', $shippingExpenseAccount->id);
        $this->assertNotNull($expenseLine);
        $this->assertSame($remittance->total_shipping_fees_minor, (int) $expenseLine->amount_minor);

        // Check COD Receivable Account has credit of -total_collected_minor
        $codReceivableAccount = $accountResolver->getPlatformCodReceivableAccount();
        $receivableLine = $lines->firstWhere('account_id', $codReceivableAccount->id);
        $this->assertNotNull($receivableLine);
        $this->assertSame(-$remittance->total_collected_minor, (int) $receivableLine->amount_minor);
    }

    public function test_merchant_can_access_cod_remittance_views(): void
    {
        [$order1, $shipment1] = $this->createDeliveredOrder('BST-VIEW-01', 39500, 4500);

        /** @var CodRemittanceService $remittanceService */
        $remittanceService = app(CodRemittanceService::class);
        $remittance = $remittanceService->createRemittance(
            courierAccount: $this->courierAccount,
            rows: [
                ['tracking_number' => 'BST-VIEW-01', 'reported_cod_minor' => 39500, 'shipping_fee_minor' => 4500],
            ],
            reference: 'REM-VIEW-01'
        );

        // 1. Index View
        $indexResponse = $this->actingAs($this->staffUser)
            ->get('https://cod-test-store.localhost/store/cod-remittances');

        $indexResponse->assertOk();
        $indexResponse->assertViewIs('store.cod.index');
        $indexResponse->assertSee($remittance->reference);

        // 2. Create View
        $createResponse = $this->actingAs($this->staffUser)
            ->get('https://cod-test-store.localhost/store/cod-remittances/create');

        $createResponse->assertOk();
        $createResponse->assertViewIs('store.cod.create');
        $createResponse->assertSee('Bosta Courier');

        // 3. Show View
        $showResponse = $this->actingAs($this->staffUser)
            ->get("https://cod-test-store.localhost/store/cod-remittances/{$remittance->id}");

        $showResponse->assertOk();
        $showResponse->assertViewIs('store.cod.show');
        $showResponse->assertSee($remittance->reference);
        $showResponse->assertSee('BST-VIEW-01');

        // 4. Submit via Web Route (Store & Settle)
        $storeResponse = $this->actingAs($this->staffUser)
            ->post('https://cod-test-store.localhost/store/cod-remittances', [
                'courier_account_id' => $this->courierAccount->id,
                'reference' => 'REM-FORM-POST-01',
                'raw_csv' => 'BST-VIEW-01,395.00,45.00,delivered',
            ]);

        $storeResponse->assertRedirect();

        $createdRemittance = CodRemittance::where('reference', 'REM-FORM-POST-01')->firstOrFail();

        $settleResponse = $this->actingAs($this->staffUser)
            ->post("https://cod-test-store.localhost/store/cod-remittances/{$createdRemittance->id}/settle", [
                'bank_reference' => 'TXN-ONLINE-1234',
            ]);

        $settleResponse->assertRedirect();
        $createdRemittance->refresh();
        $this->assertSame(CodRemittanceStatus::SETTLED, $createdRemittance->status);
    }

    public function test_cod_reconciliation_feature_flag_is_enforced(): void
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
            ->get('https://cod-test-store.localhost/store/cod-remittances');
    }
}
