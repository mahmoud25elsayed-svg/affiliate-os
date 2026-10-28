<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Domain\Finance\DTOs\EntryData;
use App\Domain\Finance\DTOs\LedgerLineData;
use App\Domain\Finance\Exceptions\ImmutableFinancialRecordException;
use App\Domain\Finance\Exceptions\UnbalancedLedgerEntryException;
use App\Domain\Finance\Services\AccountResolver;
use App\Domain\Finance\Services\CommissionSettlementService;
use App\Domain\Finance\Services\Ledger;
use App\Domain\Orders\DTOs\OrderActor;
use App\Domain\Orders\Enums\OrderStatus;
use App\Domain\Orders\Services\OrderTransitionService;
use App\Models\Account;
use App\Models\Affiliate;
use App\Models\JournalEntry;
use App\Models\LedgerLine;
use App\Models\Tenant;
use App\Models\Warehouse;
use App\Models\WholesalePrice;
use App\Support\Enums\ReasonCode;
use App\Support\Location\Governorate;
use Database\Seeders\EgyptGovernoratesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Lunar\Base\ValueObjects\Cart\TaxBreakdown;
use Lunar\FieldTypes\Text;
use Lunar\Models\Channel;
use Lunar\Models\Currency;
use Lunar\Models\Language;
use Lunar\Models\Order;
use Lunar\Models\Product;
use Lunar\Models\ProductType;
use Lunar\Models\ProductVariant;
use Lunar\Models\TaxClass;
use Tests\TestCase;

class DoubleEntryLedgerAndWalletTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected string $domain = 'finance.affiliate.test';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(EgyptGovernoratesSeeder::class);

        /** @var Tenant $tenant */
        $tenant = Tenant::create([
            'id' => 'test-tenant-finance',
            'name' => 'متجر المالية المزدوجة',
        ]);
        $this->tenant = $tenant;

        $this->tenant->domains()->create([
            'domain' => $this->domain,
        ]);

        $this->tenant->run(function () {
            Channel::firstOrCreate(
                ['handle' => 'webstore'],
                ['name' => 'Webstore', 'default' => true, 'url' => "https://{$this->domain}"]
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

    protected function deliverOrder(Order $order, OrderTransitionService $transitionService): Order
    {
        $actor = OrderActor::system();
        $order = $transitionService->transition($order, OrderStatus::PENDING_CONFIRMATION, null, $actor);
        $order = $transitionService->transition($order, OrderStatus::CONFIRMED, null, $actor);
        $order = $transitionService->transition($order, OrderStatus::RESERVED, null, $actor);
        $order = $transitionService->transition($order, OrderStatus::PACKED, null, $actor);
        $order = $transitionService->transition($order, OrderStatus::SHIPPED, null, $actor);
        $order = $transitionService->transition($order, OrderStatus::OUT_FOR_DELIVERY, null, $actor);

        return $transitionService->transition($order, OrderStatus::DELIVERED, null, $actor);
    }

    protected function createOrderWithAffiliate(Affiliate $affiliate, int $profitMinor = 5000): Order
    {
        $productType = ProductType::first() ?? ProductType::create(['name' => 'Default']);
        $product = Product::create([
            'product_type_id' => $productType->id,
            'status' => 'published',
            'attribute_data' => ['name' => new Text('منتج تجربة مالية')],
        ]);

        /** @var ProductVariant $variant */
        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'tax_class_id' => 1,
            'sku' => 'FIN-'.uniqid(),
            'unit_quantity' => 1,
        ]);

        WholesalePrice::create([
            'product_variant_id' => $variant->id,
            'wholesale_price_minor' => 10000,
            'min_selling_price_minor' => 12000,
            'max_selling_price_minor' => 20000,
            'status' => 'active',
        ]);

        $channel = Channel::first();

        /** @var Order $order */
        $order = Order::create([
            'channel_id' => $channel ? $channel->id : 1,
            'status' => OrderStatus::SUBMITTED->value,
            'reference' => 'ORD-FIN-'.uniqid(),
            'sub_total' => 15000,
            'discount_total' => 0,
            'shipping_total' => 5000,
            'tax_breakdown' => new TaxBreakdown,
            'tax_total' => 0,
            'total' => 20000,
            'currency_code' => 'EGP',
            'compare_currency_code' => 'EGP',
            'exchange_rate' => 1.0,
            'placed_at' => now(),
            'affiliate_id' => $affiliate->id,
            'customer_phone_e164' => '+201012345678',
            'customer_phone_hash' => hash('sha256', '+201012345678'),
            'price_snapshot' => json_encode([
                'affiliate_profit_minor' => $profitMinor,
            ]),
            'meta' => [
                'affiliate_commission_minor' => $profitMinor,
            ],
        ]);

        return $order;
    }

    public function test_property_test_one_thousand_random_balanced_entries(): void
    {
        $this->tenant->run(function () {
            $ledger = app(Ledger::class);
            $accountResolver = app(AccountResolver::class);

            /** @var Affiliate $affiliate1 */
            $affiliate1 = Affiliate::create(['name' => 'مسوق 1', 'phone' => '+201011110001', 'password' => 'secret', 'code' => 'P1']);
            /** @var Affiliate $affiliate2 */
            $affiliate2 = Affiliate::create(['name' => 'مسوق 2', 'phone' => '+201011110002', 'password' => 'secret', 'code' => 'P2']);
            /** @var Affiliate $affiliate3 */
            $affiliate3 = Affiliate::create(['name' => 'مسوق 3', 'phone' => '+201011110003', 'password' => 'secret', 'code' => 'P3']);

            $accounts = [
                $accountResolver->getAffiliatePendingAccount($affiliate1),
                $accountResolver->getAffiliateAvailableAccount($affiliate1),
                $accountResolver->getAffiliatePendingAccount($affiliate2),
                $accountResolver->getAffiliateAvailableAccount($affiliate2),
                $accountResolver->getAffiliatePendingAccount($affiliate3),
                $accountResolver->getAffiliateAvailableAccount($affiliate3),
                $accountResolver->getStoreRevenueAccount(),
                $accountResolver->getStoreCommissionExpenseAccount(),
                $accountResolver->getPlatformCodReceivableAccount(),
            ];

            // Deterministic pseudo-random seed
            mt_srand(123456);

            for ($i = 1; $i <= 1000; $i++) {
                // Pick 2 distinct accounts
                $idx1 = mt_rand(0, count($accounts) - 1);
                do {
                    $idx2 = mt_rand(0, count($accounts) - 1);
                } while ($idx2 === $idx1);

                $acc1 = $accounts[$idx1];
                $acc2 = $accounts[$idx2];

                $amount = mt_rand(100, 50000); // 1.00 to 500.00 EGP

                $entryData = new EntryData(
                    type: 'random_property_test',
                    idempotencyKey: "prop_test_{$i}",
                    description: "Property test entry {$i}",
                    lines: [
                        new LedgerLineData(account: $acc1, amountMinor: $amount),
                        new LedgerLineData(account: $acc2, amountMinor: -$amount),
                    ]
                );

                $ledger->post($entryData);
            }

            // Invariant A: Sum of all accounts in the entire system must equal 0
            $allAccountsSum = (int) Account::sum('balance_minor');
            $this->assertSame(0, $allAccountsSum, 'مجموع كل الحسابات في النظام يجب أن يساوي صفراً دائماً.');

            // Invariant B: For EVERY account, balance_minor must match exactly the sum of its ledger_lines
            foreach ($accounts as $acc) {
                $acc->refresh();
                $linesSum = (int) LedgerLine::where('account_id', $acc->id)->sum('amount_minor');
                $this->assertSame($acc->balance_minor, $linesSum, "رصيد الحساب {$acc->code} لا يطابق مجموع سطوره الدفترية!");
            }
        });
    }

    public function test_idempotency_prevents_duplicate_posting(): void
    {
        $this->tenant->run(function () {
            $ledger = app(Ledger::class);
            $accountResolver = app(AccountResolver::class);

            /** @var Affiliate $affiliate */
            $affiliate = Affiliate::create(['name' => 'مسوق التكرار', 'phone' => '+201011119999', 'password' => 'secret', 'code' => 'IDEM1']);

            $pending = $accountResolver->getAffiliatePendingAccount($affiliate);
            $expense = $accountResolver->getStoreCommissionExpenseAccount();

            $entryData = new EntryData(
                type: 'test_idempotency',
                idempotencyKey: 'same_event_key_123',
                description: 'حدث تجربة التكرار',
                lines: [
                    new LedgerLineData(account: $pending, amountMinor: 7500),
                    new LedgerLineData(account: $expense, amountMinor: -7500),
                ]
            );

            // Post 5 times with the exact same idempotency key
            for ($i = 0; $i < 5; $i++) {
                $ledger->post($entryData);
            }

            // Exactly 1 journal entry exists
            $this->assertSame(1, JournalEntry::where('idempotency_key', 'same_event_key_123')->count());
            $this->assertSame(2, LedgerLine::count());

            $pending->refresh();
            $expense->refresh();

            // Balance increased exactly once (75.00 EGP = 7500 minor)
            $this->assertSame(7500, $pending->balance_minor);
            $this->assertSame(-7500, $expense->balance_minor);
        });
    }

    public function test_unbalanced_entry_is_strictly_rejected(): void
    {
        $this->tenant->run(function () {
            $ledger = app(Ledger::class);
            $accountResolver = app(AccountResolver::class);

            /** @var Affiliate $affiliate */
            $affiliate = Affiliate::create(['name' => 'مسوق التوازن', 'phone' => '+201022223333', 'password' => 'secret', 'code' => 'BAL1']);
            $pending = $accountResolver->getAffiliatePendingAccount($affiliate);
            $expense = $accountResolver->getStoreCommissionExpenseAccount();

            $this->expectException(UnbalancedLedgerEntryException::class);

            $entryData = new EntryData(
                type: 'unbalanced_test',
                idempotencyKey: 'unbalanced_key_1',
                description: 'قيد غير متوازن',
                lines: [
                    new LedgerLineData(account: $pending, amountMinor: 5000),
                    new LedgerLineData(account: $expense, amountMinor: -4000), // Difference of 1000!
                ]
            );

            $ledger->post($entryData);
        });
    }

    public function test_journal_entries_and_ledger_lines_are_strictly_immutable(): void
    {
        $this->tenant->run(function () {
            $ledger = app(Ledger::class);
            $accountResolver = app(AccountResolver::class);

            /** @var Affiliate $affiliate */
            $affiliate = Affiliate::create(['name' => 'مسوق الثبات', 'phone' => '+201033334444', 'password' => 'secret', 'code' => 'IMM1']);
            $pending = $accountResolver->getAffiliatePendingAccount($affiliate);
            $expense = $accountResolver->getStoreCommissionExpenseAccount();

            $entry = $ledger->post(new EntryData(
                type: 'immutability_test',
                idempotencyKey: 'immutability_key_1',
                description: 'قيد اختبار عدم القابلية للتعديل',
                lines: [
                    new LedgerLineData(account: $pending, amountMinor: 2000),
                    new LedgerLineData(account: $expense, amountMinor: -2000),
                ]
            ));

            /** @var LedgerLine $line */
            $line = $entry->lines->firstOrFail();

            // 1. Attempt updating JournalEntry
            try {
                $entry->description = 'تعديل ملغي';
                $entry->save();
                $this->fail('كان من المتوقع إطلاق ImmutableFinancialRecordException عند تعديل القيد.');
            } catch (ImmutableFinancialRecordException $e) {
                $this->assertStringContainsString('لا يمكن تعديل', $e->getMessage());
            }

            // 2. Attempt deleting JournalEntry
            try {
                $entry->delete();
                $this->fail('كان من المتوقع إطلاق ImmutableFinancialRecordException عند حذف القيد.');
            } catch (ImmutableFinancialRecordException $e) {
                $this->assertStringContainsString('لا يمكن حذف', $e->getMessage());
            }

            // 3. Attempt updating LedgerLine
            try {
                $line->amount_minor = 9999;
                $line->save();
                $this->fail('كان من المتوقع إطلاق ImmutableFinancialRecordException عند تعديل السطر الدفتري.');
            } catch (ImmutableFinancialRecordException $e) {
                $this->assertStringContainsString('لا يمكن تعديل', $e->getMessage());
            }

            // 4. Attempt deleting LedgerLine
            try {
                $line->delete();
                $this->fail('كان من المتوقع إطلاق ImmutableFinancialRecordException عند حذف السطر الدفتري.');
            } catch (ImmutableFinancialRecordException $e) {
                $this->assertStringContainsString('لا يمكن حذف', $e->getMessage());
            }
        });
    }

    public function test_order_lifecycle_delivery_settlement_and_return_reversal_with_debt(): void
    {
        $this->tenant->run(function () {
            $transitionService = app(OrderTransitionService::class);
            $settlementService = app(CommissionSettlementService::class);

            /** @var Affiliate $affiliate */
            $affiliate = Affiliate::create([
                'name' => 'ياسين منصور',
                'phone' => '+201088889999',
                'password' => 'secret123',
                'code' => 'YAS1',
                'status' => Affiliate::STATUS_ACTIVE,
                'kyc_status' => Affiliate::KYC_APPROVED,
            ]);

            $order = $this->createOrderWithAffiliate($affiliate, 4000); // 40.00 EGP commission
            $actor = OrderActor::system();

            // Step 1: Deliver Order -> Triggers OrderDelivered event -> pending credited!
            $order = $this->deliverOrder($order, $transitionService);

            $this->assertSame(4000, $affiliate->getPendingBalance());
            $this->assertSame(0, $affiliate->getAvailableBalance());
            $this->assertFalse($affiliate->hasDebt());

            // Step 2: Settlement -> Moves pending to available
            $settlementService->settleOrder($order);

            $this->assertSame(0, $affiliate->getPendingBalance());
            $this->assertSame(4000, $affiliate->getAvailableBalance());
            $this->assertFalse($affiliate->hasDebt());

            // Step 3: Late return after settlement -> Transition to RETURN_REQUESTED -> RETURNING -> RETURNED
            // Reversal is deducted from AVAILABLE balance!
            $order = $transitionService->transition($order, OrderStatus::RETURN_REQUESTED, ReasonCode::DEFECTIVE_PRODUCT, $actor);
            $order = $transitionService->transition($order, OrderStatus::RETURNING, null, $actor);
            $order = $transitionService->transition($order, OrderStatus::RETURNED, null, $actor);

            $this->assertSame(0, $affiliate->getPendingBalance());
            $this->assertSame(0, $affiliate->getAvailableBalance());
            $this->assertFalse($affiliate->hasDebt());

            // Now let's test DEBT (Negative Available Balance):
            // Suppose another order was delivered and settled for 30.00 EGP (available = 3000)
            $order2 = $this->createOrderWithAffiliate($affiliate, 3000);
            $order2 = $this->deliverOrder($order2, $transitionService);
            $settlementService->settleOrder($order2);

            $this->assertSame(3000, $affiliate->getAvailableBalance());

            // Suppose a 50.00 EGP late return happens on an order
            $order3 = $this->createOrderWithAffiliate($affiliate, 5000);
            $order3 = $this->deliverOrder($order3, $transitionService);
            $settlementService->settleOrder($order3); // available now = 3000 + 5000 = 8000

            // Late return of order3 reverses 5000
            $order3 = $transitionService->transition($order3, OrderStatus::RETURN_REQUESTED, ReasonCode::DEFECTIVE_PRODUCT, $actor);
            $order3 = $transitionService->transition($order3, OrderStatus::RETURNING, null, $actor);
            $order3 = $transitionService->transition($order3, OrderStatus::RETURNED, null, $actor);

            // Now reverse order2 (3000)
            $order2 = $transitionService->transition($order2, OrderStatus::RETURN_REQUESTED, ReasonCode::DEFECTIVE_PRODUCT, $actor);
            $order2 = $transitionService->transition($order2, OrderStatus::RETURNING, null, $actor);
            $order2 = $transitionService->transition($order2, OrderStatus::RETURNED, null, $actor);

            $this->assertSame(0, $affiliate->getAvailableBalance());

            // Direct late reversal on an extra order where earnings were already withdrawn
            $extraOrder = $this->createOrderWithAffiliate($affiliate, 3500);
            $extraOrder = $this->deliverOrder($extraOrder, $transitionService);
            $settlementService->settleOrder($extraOrder); // available = 3500

            // Simulate withdrawal of the 3500:
            $availAcc = app(AccountResolver::class)->getAffiliateAvailableAccount($affiliate);
            $withdrawnAcc = app(AccountResolver::class)->getAffiliateWithdrawnAccount($affiliate);
            app(Ledger::class)->post(new EntryData(
                type: 'withdrawal_payout',
                idempotencyKey: 'test_withdraw_3500',
                description: 'سحب أرباح',
                lines: [
                    new LedgerLineData(account: $availAcc, amountMinor: -3500),
                    new LedgerLineData(account: $withdrawnAcc, amountMinor: 3500),
                ]
            ));
            // Available is now 0, withdrawn is 3500.

            // Now a late return on extraOrder reverses 3500 from available:
            $extraOrder = $transitionService->transition($extraOrder, OrderStatus::RETURN_REQUESTED, ReasonCode::DEFECTIVE_PRODUCT, $actor);
            $extraOrder = $transitionService->transition($extraOrder, OrderStatus::RETURNING, null, $actor);
            $extraOrder = $transitionService->transition($extraOrder, OrderStatus::RETURNED, null, $actor);

            // Available is now -3500! (Debt!)
            $this->assertSame(-3500, $affiliate->getAvailableBalance());
            $this->assertTrue($affiliate->hasDebt());
            $this->assertSame(3500, $affiliate->getDebtMinor());

            // Withdrawal is strictly blocked due to debt!
            $this->assertFalse($affiliate->canWithdraw());
        });
    }

    public function test_nightly_ledger_reconciliation_command(): void
    {
        $this->tenant->run(function () {
            $ledger = app(Ledger::class);
            $accountResolver = app(AccountResolver::class);

            /** @var Affiliate $affiliate */
            $affiliate = Affiliate::create(['name' => 'مسوق المطابقة', 'phone' => '+201077778888', 'password' => 'secret', 'code' => 'REC1']);
            $pending = $accountResolver->getAffiliatePendingAccount($affiliate);
            $expense = $accountResolver->getStoreCommissionExpenseAccount();

            $ledger->post(new EntryData(
                type: 'reconcile_test',
                idempotencyKey: 'rec_test_1',
                description: 'حركة اختبار المطابقة',
                lines: [
                    new LedgerLineData(account: $pending, amountMinor: 10000),
                    new LedgerLineData(account: $expense, amountMinor: -10000),
                ]
            ));

            // 1. Healthy State: command succeeds with exit code 0
            $this->artisan('ledger:reconcile')->assertExitCode(0);

            // 2. Introduce artificial tamper directly via raw DB query (bypassing ledger)
            DB::table('accounts')
                ->where('id', $pending->id)
                ->update(['balance_minor' => 15000]); // 50.00 EGP artificial inflation!

            // 3. Tampered State: command detects mismatch and fails with exit code 1
            $this->artisan('ledger:reconcile')->assertExitCode(1);
        });
    }

    public function test_wallet_index_and_csv_export(): void
    {
        $this->tenant->run(function () {
            $ledger = app(Ledger::class);
            $accountResolver = app(AccountResolver::class);

            /** @var Affiliate $affiliate */
            $affiliate = Affiliate::create([
                'name' => 'مصطفى كامل',
                'phone' => '+201055556666',
                'password' => 'secret123',
                'code' => 'MOST1',
                'status' => Affiliate::STATUS_ACTIVE,
            ]);

            $available = $accountResolver->getAffiliateAvailableAccount($affiliate);
            $expense = $accountResolver->getStoreCommissionExpenseAccount();

            $ledger->post(new EntryData(
                type: 'sample_movement',
                idempotencyKey: 'sample_move_1',
                description: 'أرباح تسوية طلب تجريبي',
                lines: [
                    new LedgerLineData(account: $available, amountMinor: 12000),
                    new LedgerLineData(account: $expense, amountMinor: -12000),
                ]
            ));

            // 1. Visit Wallet Index Page
            $response = $this->actingAs($affiliate, 'affiliate')
                ->get("http://{$this->domain}/partners/wallet");

            $response->assertStatus(200);
            $response->assertSee('120.00');
            $response->assertSee('أرباح تسوية طلب تجريبي');

            // 2. Export Statement as CSV
            $csvResponse = $this->actingAs($affiliate, 'affiliate')
                ->get("http://{$this->domain}/partners/wallet/export");

            $csvResponse->assertStatus(200);
            $this->assertTrue(str_contains((string) $csvResponse->headers->get('content-type'), 'text/csv'));
        });
    }
}
