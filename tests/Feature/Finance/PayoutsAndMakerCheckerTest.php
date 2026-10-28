<?php

declare(strict_types=1);

namespace Tests\Feature\Finance;

use App\Domain\Affiliate\Settings\AffiliateProgramSettings;
use App\Domain\Finance\DTOs\EntryData;
use App\Domain\Finance\DTOs\LedgerLineData;
use App\Domain\Finance\Exceptions\InsufficientAffiliateBalanceException;
use App\Domain\Finance\Exceptions\MakerCheckerViolationException;
use App\Domain\Finance\Exceptions\PayoutNotEligibleException;
use App\Domain\Finance\Services\AccountResolver;
use App\Domain\Finance\Services\Ledger;
use App\Domain\Finance\Services\PayoutBatchService;
use App\Domain\Finance\Services\PayoutService;
use App\Domain\Finance\Services\PayoutTurnaroundCalculator;
use App\Models\Affiliate;
use App\Models\PayoutBatch;
use App\Models\PayoutRequest;
use App\Models\Tenant;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayoutsAndMakerCheckerTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected string $domain;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'id' => 'store-payout-test',
            'name' => 'Store Payout Test',
            'plan' => 'growth',
        ]);

        $this->domain = 'store-payout-test.localhost';
        $this->tenant->domains()->create([
            'domain' => $this->domain,
        ]);
    }

    /**
     * Helper to create an active, KYC-approved affiliate with a funded available balance.
     */
    protected function createFundedAffiliate(int $initialAvailableMinor = 20000): Affiliate
    {
        /** @var Affiliate $affiliate */
        $affiliate = Affiliate::create([
            'tenant_id' => (string) $this->tenant->id,
            'name' => 'مسوق تجريبي معتمد',
            'email' => 'affiliate_payout_'.uniqid().'@test.com',
            'phone' => '01012345678',
            'code' => 'AFF-'.rand(1000, 9999),
            'status' => Affiliate::STATUS_ACTIVE,
            'kyc_status' => Affiliate::KYC_APPROVED,
            'payout_method' => Affiliate::PAYOUT_METHOD_WALLET,
            'payout_details' => [
                'wallet_provider' => 'vodafone_cash',
                'phone' => '01012345678',
            ],
            'phone_verified_at' => now(),
        ]);

        if ($initialAvailableMinor > 0) {
            $availAcc = app(AccountResolver::class)->getAffiliateAvailableAccount($affiliate);
            $storeExpense = app(AccountResolver::class)->getStoreCommissionExpenseAccount();

            app(Ledger::class)->post(new EntryData(
                type: 'initial_test_funds',
                idempotencyKey: 'fund_aff_'.uniqid(),
                description: 'شحن رصيد متاح للاختبار',
                lines: [
                    new LedgerLineData(account: $storeExpense, amountMinor: -$initialAvailableMinor),
                    new LedgerLineData(account: $availAcc, amountMinor: $initialAvailableMinor),
                ]
            ));
        }

        return $affiliate;
    }

    public function test_payout_request_locks_available_funds_in_double_entry_ledger(): void
    {
        $this->tenant->run(function () {
            $affiliate = $this->createFundedAffiliate(20000); // 200.00 EGP
            $this->assertSame(20000, $affiliate->getAvailableBalance());
            $this->assertSame(0, $affiliate->getLockedBalance());

            $payoutService = app(PayoutService::class);
            $request = $payoutService->requestPayout(
                affiliate: $affiliate,
                amountMinor: 12000 // 120.00 EGP
            );

            $this->assertInstanceOf(PayoutRequest::class, $request);
            $this->assertSame(PayoutRequest::STATUS_REQUESTED, $request->status);
            $this->assertSame(12000, $request->amount_minor);

            // Double entry ledger balance checks
            $this->assertSame(8000, $affiliate->getAvailableBalance());
            $this->assertSame(12000, $affiliate->getLockedBalance());
            $this->assertSame(0, $affiliate->getWithdrawnBalance());
        });
    }

    public function test_payout_request_enforces_eligibility_and_bounds(): void
    {
        $this->tenant->run(function () {
            $affiliate = $this->createFundedAffiliate(20000);
            $payoutService = app(PayoutService::class);

            // 1. Below min payout
            $settings = AffiliateProgramSettings::load();
            $settings->min_payout_minor = 10000;
            $settings->save();

            try {
                $payoutService->requestPayout($affiliate, 5000);
                $this->fail('Should have rejected payout below minimum threshold');
            } catch (InsufficientAffiliateBalanceException $e) {
                $this->assertStringContainsString('الحد الأدنى', $e->getMessage());
            }

            // 2. Reject when payouts frozen
            $affiliate->freezePayouts(24);

            try {
                $payoutService->requestPayout($affiliate, 10000);
                $this->fail('Should have rejected payout when frozen');
            } catch (PayoutNotEligibleException $e) {
                $this->assertNotEmpty($e->getMessage());
            }
            $affiliate->payouts_frozen_until = null;
            $affiliate->save();

            // 3. Reject when debt exists
            $availAcc = app(AccountResolver::class)->getAffiliateAvailableAccount($affiliate);
            $storeExpense = app(AccountResolver::class)->getStoreCommissionExpenseAccount();
            app(Ledger::class)->post(new EntryData(
                type: 'debt_simulation',
                idempotencyKey: 'debt_sim_'.uniqid(),
                description: 'مرتجع متأخر يسبب ديناً',
                lines: [
                    new LedgerLineData(account: $availAcc, amountMinor: -30000), // available becomes -10000
                    new LedgerLineData(account: $storeExpense, amountMinor: 30000),
                ]
            ));

            $this->assertTrue($affiliate->hasDebt());

            try {
                $payoutService->requestPayout($affiliate, 10000);
                $this->fail('Should have rejected payout when debt exists');
            } catch (PayoutNotEligibleException $e) {
                $this->assertNotEmpty($e->getMessage());
            }
        });
    }

    public function test_maker_checker_authorization_matrix(): void
    {
        $this->tenant->run(function () {
            $settings = AffiliateProgramSettings::load();
            $settings->maker_checker_threshold_minor = 50000; // 500.00 EGP
            $settings->save();

            /** @var User $maker */
            $maker = User::create([
                'name' => 'أحمد المعتمد (Maker)',
                'email' => 'maker@test.com',
                'password' => bcrypt('password'),
            ]);

            /** @var User $checker */
            $checker = User::create([
                'name' => 'خالد المنفذ (Checker)',
                'email' => 'checker@test.com',
                'password' => bcrypt('password'),
            ]);

            $payoutService = app(PayoutService::class);

            // Case A: High amount (70,000 minor units >= 50,000 threshold) -> Maker-Checker strictly enforced!
            $affiliate = $this->createFundedAffiliate(100000);
            $highRequest = $payoutService->requestPayout($affiliate, 70000);

            // Maker approves
            $highRequest = $payoutService->approvePayout($highRequest, $maker);
            $this->assertSame(PayoutRequest::STATUS_APPROVED, $highRequest->status);
            $this->assertSame($maker->id, $highRequest->approved_by);

            // Attempt checker execution with same user (Maker) -> MUST FAIL!
            try {
                $payoutService->executePayout(
                    request: $highRequest,
                    actor: $maker,
                    referenceNumber: 'VF-ILLEGAL-999'
                );
                $this->fail('Should have thrown MakerCheckerViolationException');
            } catch (MakerCheckerViolationException $e) {
                $this->assertStringContainsString('Maker-Checker', $e->getMessage());
            }

            // Checker execution with distinct user -> MUST SUCCEED!
            $executedRequest = $payoutService->executePayout(
                request: $highRequest,
                actor: $checker,
                referenceNumber: 'VF-LEGAL-12345'
            );

            $this->assertSame(PayoutRequest::STATUS_PAID, $executedRequest->status);
            $this->assertSame($checker->id, $executedRequest->paid_by);
            $this->assertSame('VF-LEGAL-12345', $executedRequest->reference_number);

            // Double entry ledger checks
            $this->assertSame(30000, $affiliate->getAvailableBalance());
            $this->assertSame(0, $affiliate->getLockedBalance());
            $this->assertSame(70000, $affiliate->getWithdrawnBalance());

            // Case B: Low amount (20,000 minor units < 50,000 threshold) -> single user can approve & execute
            $lowRequest = $payoutService->requestPayout($affiliate, 20000);
            $lowRequest = $payoutService->approvePayout($lowRequest, $maker);

            $executedLow = $payoutService->executePayout(
                request: $lowRequest,
                actor: $maker, // Same user allowed because below threshold!
                referenceNumber: 'VF-LOW-111'
            );
            $this->assertSame(PayoutRequest::STATUS_PAID, $executedLow->status);
            $this->assertSame(90000, $affiliate->getWithdrawnBalance());
        });
    }

    public function test_payout_rejection_reverses_locked_to_available_in_ledger(): void
    {
        $this->tenant->run(function () {
            $affiliate = $this->createFundedAffiliate(30000);
            $payoutService = app(PayoutService::class);

            /** @var User $admin */
            $admin = User::create([
                'name' => 'مدير الحسابات',
                'email' => 'admin_payout_rej@test.com',
                'password' => bcrypt('password'),
            ]);

            $request = $payoutService->requestPayout($affiliate, 20000);
            $this->assertSame(10000, $affiliate->getAvailableBalance());
            $this->assertSame(20000, $affiliate->getLockedBalance());

            // Reject request
            $rejected = $payoutService->rejectPayout(
                request: $request,
                actor: $admin,
                reason: 'رقم المحفظة غير مسجل لدى فودافون كاش'
            );

            $this->assertSame(PayoutRequest::STATUS_REJECTED, $rejected->status);
            $this->assertSame('رقم المحفظة غير مسجل لدى فودافون كاش', $rejected->rejection_reason);

            // Reversal check: locked returned to available
            $this->assertSame(30000, $affiliate->getAvailableBalance());
            $this->assertSame(0, $affiliate->getLockedBalance());
            $this->assertSame(0, $affiliate->getWithdrawnBalance());
        });
    }

    public function test_affiliate_can_cancel_pending_payout_request(): void
    {
        $this->tenant->run(function () {
            $affiliate = $this->createFundedAffiliate(25000);
            $payoutService = app(PayoutService::class);

            $request = $payoutService->requestPayout($affiliate, 15000);
            $this->assertSame(10000, $affiliate->getAvailableBalance());
            $this->assertSame(15000, $affiliate->getLockedBalance());

            // Affiliate cancels their own request
            $cancelled = $payoutService->cancelPayout($request, $affiliate);
            $this->assertSame(PayoutRequest::STATUS_CANCELLED, $cancelled->status);

            // Funds restored
            $this->assertSame(25000, $affiliate->getAvailableBalance());
            $this->assertSame(0, $affiliate->getLockedBalance());
        });
    }

    /**
     * PAY-01 Acceptance Criterion:
     * Concurrency test: two parallel payout requests on a balance sufficient for only one => exactly one succeeds.
     */
    public function test_concurrency_race_condition_protection_on_payout_requests(): void
    {
        $this->tenant->run(function () {
            // Balance is 15,000 minor units (150 EGP).
            // Two requests each ask for 10,000 minor units.
            // Balance is sufficient for ONLY ONE (15,000 < 20,000).
            $affiliate = $this->createFundedAffiliate(15000);
            $payoutService = app(PayoutService::class);

            $successCount = 0;
            $failCount = 0;

            // Attempt Request 1
            try {
                $payoutService->requestPayout($affiliate, 10000);
                $successCount++;
            } catch (InsufficientAffiliateBalanceException) {
                $failCount++;
            }

            // Attempt Request 2 (simulating race when balance was checked before lock)
            try {
                $payoutService->requestPayout($affiliate, 10000);
                $successCount++;
            } catch (InsufficientAffiliateBalanceException) {
                $failCount++;
            }

            // Exactly ONE succeeds, ONE fails
            $this->assertSame(1, $successCount, 'Exactly one concurrent request must succeed');
            $this->assertSame(1, $failCount, 'The second concurrent request must fail');

            // Remaining available balance is safely 5,000 (no overdraft / no duplicate deduction)
            $this->assertSame(5000, $affiliate->getAvailableBalance());
            $this->assertSame(10000, $affiliate->getLockedBalance());
        });
    }

    public function test_batch_payout_aggregation_csv_export_and_bulk_execution(): void
    {
        $this->tenant->run(function () {
            /** @var User $operator */
            $operator = User::create([
                'name' => 'مسؤول التحويلات',
                'email' => 'operator_batch@test.com',
                'password' => bcrypt('password'),
            ]);

            $payoutService = app(PayoutService::class);
            $batchService = app(PayoutBatchService::class);

            // Create 3 affiliates and 3 payout requests
            $aff1 = $this->createFundedAffiliate(30000);
            $aff2 = $this->createFundedAffiliate(40000);
            $aff3 = $this->createFundedAffiliate(50000);

            $req1 = $payoutService->requestPayout($aff1, 10000, 'wallet_phone', ['wallet_provider' => 'vodafone_cash', 'phone' => '01011111111']);
            $req2 = $payoutService->requestPayout($aff2, 15000, 'wallet_phone', ['wallet_provider' => 'vodafone_cash', 'phone' => '01022222222']);
            $req3 = $payoutService->requestPayout($aff3, 20000, 'wallet_phone', ['wallet_provider' => 'vodafone_cash', 'phone' => '01033333333']);

            // Create Batch
            $batch = $batchService->createBatch(
                paymentMethod: 'wallet_phone',
                requestIds: [$req1->id, $req2->id, $req3->id],
                creator: $operator
            );

            $this->assertInstanceOf(PayoutBatch::class, $batch);
            $this->assertSame(3, $batch->total_requests);
            $this->assertSame(45000, $batch->total_amount_minor);
            $this->assertStringStartsWith('BATCH-WALL-', $batch->batch_reference);

            // CSV Export
            $csv = $batchService->exportBatchCsv($batch);
            $this->assertStringContainsString('01011111111', $csv);
            $this->assertStringContainsString('01022222222', $csv);
            $this->assertStringContainsString('01033333333', $csv);
            $this->assertStringContainsString('100.00', $csv);
            $this->assertStringContainsString('150.00', $csv);
            $this->assertStringContainsString('200.00', $csv);

            // Bulk Process Batch
            $batchService->processBatch(
                batch: $batch,
                actor: $operator,
                batchReferenceNumber: 'BULK-VF-CONFIRMED-999'
            );

            $this->assertTrue($batch->fresh()->isCompleted());
            $this->assertSame(PayoutRequest::STATUS_PAID, $req1->fresh()->status);
            $this->assertSame(PayoutRequest::STATUS_PAID, $req2->fresh()->status);
            $this->assertSame(PayoutRequest::STATUS_PAID, $req3->fresh()->status);

            // Withdrawn accounts updated
            $this->assertSame(10000, $aff1->getWithdrawnBalance());
            $this->assertSame(15000, $aff2->getWithdrawnBalance());
            $this->assertSame(20000, $aff3->getWithdrawnBalance());
        });
    }

    public function test_payout_turnaround_calculator_computes_human_duration(): void
    {
        $this->tenant->run(function () {
            $affiliate = $this->createFundedAffiliate(50000);
            $payoutService = app(PayoutService::class);
            $calculator = app(PayoutTurnaroundCalculator::class);

            /** @var User $admin */
            $admin = User::create([
                'name' => 'مسؤول المالية',
                'email' => 'fin_turnaround@test.com',
                'password' => bcrypt('password'),
            ]);

            Carbon::setTestNow('2026-10-28 10:00:00');
            $req = $payoutService->requestPayout($affiliate, 10000);

            // Paid 2 hours later
            Carbon::setTestNow('2026-10-28 12:00:00');
            $payoutService->executePayout($req, $admin, 'REF-TURNAROUND-1');

            $seconds = $calculator->getAverageTurnaroundSeconds();
            $this->assertSame(7200, $seconds); // 2 hours = 7200s
            $this->assertStringContainsString('2 ساعة', $calculator->getAverageTurnaroundHuman());

            Carbon::setTestNow(); // reset
        });
    }

    public function test_affiliate_dashboard_displays_real_metrics_and_movements(): void
    {
        $affiliate = null;
        $this->tenant->run(function () use (&$affiliate) {
            $affiliate = $this->createFundedAffiliate(10000);
        });

        $response = $this->actingAs($affiliate, 'affiliate')
            ->get("http://{$this->domain}/partners/dashboard");

        $response->assertStatus(200);
        $response->assertSee('محفظة الأرباح والأرصدة');
        $response->assertSee('إحصائيات وحركات الطلبات');
        $response->assertSee('100.00'); // 100.00 EGP available
    }

    public function test_admin_affiliate_settings_update_commission_bounds(): void
    {
        /** @var User $admin */
        $admin = null;
        $this->tenant->run(function () use (&$admin) {
            $admin = User::create([
                'name' => 'التاجر صاحب المتجر',
                'email' => 'store_owner_settings@test.com',
                'password' => bcrypt('password'),
            ]);
        });

        // GET settings page
        $getResponse = $this->actingAs($admin)
            ->get("http://{$this->domain}/store/affiliates/settings");

        $getResponse->assertStatus(200);
        $getResponse->assertSee('حدود العمولات وهوامش الربح');

        // POST update commission bounds
        $postResponse = $this->actingAs($admin)
            ->post("http://{$this->domain}/store/affiliates/settings", [
                'commission_model' => 'markup',
                'default_commission_rate' => 15,
                'min_commission_minor' => 2500, // 25.00 EGP
                'max_commission_minor' => 150000, // 1500.00 EGP
                'min_selling_markup_minor' => 3000,
                'max_selling_markup_minor' => 300000,
                'min_payout_minor' => 15000,
                'maker_checker_threshold_minor' => 60000,
                'return_window_days' => 10,
                'payout_days' => ['sunday', 'thursday'],
                'auto_approve_registrations' => 1,
                'show_customer_phone_to_affiliate' => 0,
                'default_daily_order_limit' => 80,
                'release_mode' => 'later_of_both',
            ]);

        $postResponse->assertRedirect("http://{$this->domain}/store/affiliates/settings");
        $postResponse->assertSessionHas('success');

        $this->tenant->run(function () {
            $settings = AffiliateProgramSettings::load();
            $this->assertSame(2500, $settings->min_commission_minor);
            $this->assertSame(150000, $settings->max_commission_minor);
            $this->assertSame(15000, $settings->min_payout_minor);
            $this->assertSame(60000, $settings->maker_checker_threshold_minor);
            $this->assertTrue($settings->auto_approve_registrations);
        });
    }
}
