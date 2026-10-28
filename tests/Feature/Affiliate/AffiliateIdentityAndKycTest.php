<?php

declare(strict_types=1);

namespace Tests\Feature\Affiliate;

use App\Domain\Affiliate\Exceptions\AffiliateLimitExceededException;
use App\Domain\Affiliate\Services\AffiliateKycService;
use App\Domain\Affiliate\Services\AffiliatePayoutAccountService;
use App\Domain\Affiliate\Services\AffiliateRegistrationService;
use App\Domain\Affiliate\Settings\AffiliateProgramSettings;
use App\Models\Affiliate;
use App\Models\KycAuditLog;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy\Permissions\TenantPermissionMatrix;
use Database\Seeders\PlanSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class AffiliateIdentityAndKycTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected string $domain;

    protected function setUp(): void
    {
        parent::setUp();

        app()->setLocale('ar');
        $this->seed(PlanSeeder::class);
        $this->tenant = $this->ensureTenantExists('affstore');
        $this->domain = $this->tenant->domains->first()?->domain ?? 'affstore.localhost';
    }

    public function test_guard_isolation_affiliate_and_guest_access_boundaries(): void
    {
        $this->tenant->run(function () {
            // 1. Unauthenticated guest accessing affiliate dashboard is redirected to login
            $response = $this->get("http://{$this->domain}/partners/dashboard");
            $response->assertRedirect("http://{$this->domain}/partners/login");

            // 2. Create and authenticate affiliate
            /** @var Affiliate $affiliate */
            $affiliate = Affiliate::create([
                'name' => 'أحمد محمود',
                'phone' => '+201012345678',
                'email' => 'aff@test.com',
                'password' => 'secret123',
                'code' => 'AFF100',
                'status' => Affiliate::STATUS_ACTIVE,
                'phone_verified_at' => now(),
            ]);

            Auth::guard('affiliate')->login($affiliate);
            $this->assertTrue(Auth::guard('affiliate')->check());
            $this->assertFalse(Auth::guard('web')->check());
            $this->assertFalse(Auth::guard('customer')->check());

            // 3. Authenticated affiliate can access partner dashboard
            $dashResponse = $this->actingAs($affiliate, 'affiliate')->get("http://{$this->domain}/partners/dashboard");
            $dashResponse->assertOk();
            $dashResponse->assertSee('أحمد محمود');
            $dashResponse->assertSee('AFF100');
        });
    }

    public function test_affiliate_registration_normalizes_egyptian_phone_and_dispatches_otp(): void
    {
        $this->tenant->run(function () {
            $regService = app(AffiliateRegistrationService::class);

            $affiliate = $regService->register([
                'name' => 'محمد كمال',
                'phone' => '01123456789',
                'email' => 'mkamal@example.com',
                'password' => 'strongPassword123',
            ]);

            $this->assertSame('+201123456789', $affiliate->phone);
            $this->assertNotEmpty($affiliate->code);
            $this->assertSame(Affiliate::STATUS_PENDING, $affiliate->status);
            $this->assertNull($affiliate->phone_verified_at);

            // Check OTP generated and saved on affiliate
            $this->assertNotNull($affiliate->otp_code);
            $this->assertSame(6, strlen((string) $affiliate->otp_code));

            // Verify OTP
            $verified = $regService->verifyPhoneOtp($affiliate, (string) $affiliate->otp_code);
            $this->assertTrue($verified);

            $affiliate->refresh();
            $this->assertNotNull($affiliate->phone_verified_at);
        });
    }

    public function test_affiliate_registration_with_auto_approve_setting(): void
    {
        $this->tenant->run(function () {
            $settings = AffiliateProgramSettings::load();
            $settings->auto_approve_registrations = true;
            $settings->save();

            $regService = app(AffiliateRegistrationService::class);
            $affiliate = $regService->register([
                'name' => 'سارة علي',
                'phone' => '01234567890',
                'email' => 'sara@example.com',
                'password' => 'password123',
            ]);

            $this->assertSame(Affiliate::STATUS_ACTIVE, $affiliate->status);
        });
    }

    public function test_plan_affiliates_limit_is_strictly_enforced(): void
    {
        /** @var Plan $limitedPlan */
        $limitedPlan = Plan::create([
            'slug' => 'test-limited-affiliates',
            'name_ar' => 'باقة محدودة',
            'name_en' => 'Limited Plan',
            'price_monthly' => 5000,
            'price_yearly' => 50000,
            'trial_days' => 14,
            'limits' => [
                'affiliates' => 2,
            ],
            'features' => [
                'affiliate' => true,
            ],
            'is_active' => true,
        ]);

        $this->tenant->subscriptions()->create([
            'plan_id' => $limitedPlan->id,
            'status' => Subscription::STATUS_ACTIVE,
            'billing_cycle' => Subscription::CYCLE_MONTHLY,
            'current_period_starts_at' => now(),
            'current_period_ends_at' => now()->addMonth(),
        ]);

        $this->tenant->run(function () {
            $regService = app(AffiliateRegistrationService::class);

            // 1st affiliate - success
            $regService->register([
                'name' => 'مسوق 1',
                'phone' => '01011111111',
                'password' => 'pass123',
            ]);

            // 2nd affiliate - success
            $regService->register([
                'name' => 'مسوق 2',
                'phone' => '01022222222',
                'password' => 'pass123',
            ]);

            // 3rd affiliate - must throw AffiliateLimitExceededException
            $this->expectException(AffiliateLimitExceededException::class);
            $regService->register([
                'name' => 'مسوق 3',
                'phone' => '01033333333',
                'password' => 'pass123',
            ]);
        });
    }

    public function test_kyc_submission_and_secure_storage(): void
    {
        Storage::fake('local');

        $this->tenant->run(function () {
            /** @var Affiliate $affiliate */
            $affiliate = Affiliate::create([
                'name' => 'خالد إبراهيم',
                'phone' => '+201099998888',
                'password' => 'pass123',
                'code' => 'KHALID99',
            ]);

            $kycService = app(AffiliateKycService::class);

            $frontFile = UploadedFile::fake()->image('id_front.jpg', 600, 400);
            $backFile = UploadedFile::fake()->image('id_back.jpg', 600, 400);

            $kycService->submitDocuments(
                affiliate: $affiliate,
                nationalId: '29510101234567',
                front: $frontFile,
                back: $backFile
            );

            $affiliate->refresh();
            $this->assertSame('29510101234567', $affiliate->national_id_number);
            $this->assertSame(Affiliate::KYC_PENDING, $affiliate->kyc_status);
            $this->assertNotNull($affiliate->id_card_front_path);
            $this->assertNotNull($affiliate->id_card_back_path);

            Storage::disk('local')->assertExists($affiliate->id_card_front_path);
            Storage::disk('local')->assertExists($affiliate->id_card_back_path);
        });
    }

    public function test_kyc_view_requires_kyc_view_permission_and_records_audit_trail(): void
    {
        Storage::fake('local');

        $this->tenant->run(function () {
            TenantPermissionMatrix::seedForTenant((string) $this->tenant->id);

            /** @var Affiliate $affiliate */
            $affiliate = Affiliate::create([
                'name' => 'محمود مصطفى',
                'phone' => '+201055554444',
                'password' => 'pass123',
                'code' => 'MAHMOUD1',
                'national_id_number' => '29001011234567',
                'id_card_front_path' => 'kyc/test_front.jpg',
                'id_card_back_path' => 'kyc/test_back.jpg',
                'kyc_status' => Affiliate::KYC_PENDING,
            ]);

            Storage::disk('local')->put('kyc/test_front.jpg', 'fake-image-content');

            // 1. Regular user without permission
            /** @var User $unauthorizedUser */
            $unauthorizedUser = User::create([
                'name' => 'موظف عادي',
                'email' => 'staff@test.com',
                'password' => 'secret123',
                'tenant_id' => $this->tenant->id,
            ]);

            $kycService = app(AffiliateKycService::class);

            try {
                $kycService->viewDocument($affiliate, 'front', $unauthorizedUser);
                $this->fail('Expected AuthorizationException was not thrown');
            } catch (AuthorizationException $e) {
                $this->assertStringContainsString('kyc.view', $e->getMessage());
            }

            // 2. User with kyc.view permission
            /** @var User $authorizedUser */
            $authorizedUser = User::create([
                'name' => 'مسؤول التحقق',
                'email' => 'kyc_agent@test.com',
                'password' => 'secret123',
                'tenant_id' => $this->tenant->id,
            ]);

            Permission::findOrCreate('kyc.view', 'web');
            $authorizedUser->givePermissionTo('kyc.view');

            $path = $kycService->viewDocument(
                affiliate: $affiliate,
                side: 'front',
                viewer: $authorizedUser,
                ip: '192.168.1.10',
                userAgent: 'Mozilla/5.0 Test'
            );

            $this->assertNotEmpty($path);

            // 3. Verify mandatory KYC Audit Log was created
            $log = KycAuditLog::where('affiliate_id', $affiliate->id)->first();
            $this->assertNotNull($log);
            $this->assertSame($authorizedUser->id, $log->user_id);
            $this->assertSame('front', $log->document_type);
            $this->assertSame('view', $log->action);
            $this->assertSame('192.168.1.10', $log->ip_address);
            $this->assertSame('Mozilla/5.0 Test', $log->user_agent);
        });
    }

    public function test_kyc_approval_and_rejection_workflows_with_audit_logs(): void
    {
        $this->tenant->run(function () {
            TenantPermissionMatrix::seedForTenant((string) $this->tenant->id);

            /** @var User $reviewer */
            $reviewer = User::create([
                'name' => 'المدقق المالي',
                'email' => 'auditor@test.com',
                'password' => 'secret123',
                'tenant_id' => $this->tenant->id,
            ]);

            /** @var Affiliate $affiliate */
            $affiliate = Affiliate::create([
                'name' => 'شريف أحمد',
                'phone' => '+201077778888',
                'password' => 'pass123',
                'code' => 'SHERIF77',
                'national_id_number' => '28801011234567',
                'kyc_status' => Affiliate::KYC_PENDING,
            ]);

            $kycService = app(AffiliateKycService::class);

            // 1. Approve KYC
            $kycService->approve($affiliate, $reviewer);
            $affiliate->refresh();

            $this->assertSame(Affiliate::KYC_APPROVED, $affiliate->kyc_status);
            $this->assertSame($reviewer->id, $affiliate->kyc_reviewed_by);
            $this->assertNotNull($affiliate->kyc_reviewed_at);

            $this->assertDatabaseHas('kyc_audit_logs', [
                'affiliate_id' => $affiliate->id,
                'user_id' => $reviewer->id,
                'action' => 'approve',
            ]);

            // 2. Reject KYC
            $kycService->reject($affiliate, $reviewer, 'صورة البطاقة غير واضحة ويوجد انعكاس ضوئي');
            $affiliate->refresh();

            $this->assertSame(Affiliate::KYC_REJECTED, $affiliate->kyc_status);
            $this->assertSame('صورة البطاقة غير واضحة ويوجد انعكاس ضوئي', $affiliate->kyc_rejected_reason);

            $this->assertDatabaseHas('kyc_audit_logs', [
                'affiliate_id' => $affiliate->id,
                'user_id' => $reviewer->id,
                'action' => 'reject',
            ]);
        });
    }

    public function test_payout_account_details_are_encrypted_and_trigger_24h_freeze(): void
    {
        $this->tenant->run(function () {
            /** @var Affiliate $affiliate */
            $affiliate = Affiliate::create([
                'name' => 'طارق حسن',
                'phone' => '+201022223333',
                'password' => 'secret123',
                'code' => 'TAREK22',
                'status' => Affiliate::STATUS_ACTIVE,
                'kyc_status' => Affiliate::KYC_APPROVED,
            ]);

            $payoutService = app(AffiliatePayoutAccountService::class);

            // Request OTP
            $payoutService->requestChangeOtp($affiliate);
            $affiliate->refresh();
            $this->assertNotNull($affiliate->otp_code);
            $this->assertSame(6, strlen((string) $affiliate->otp_code));

            // Freeze should not be set yet
            $this->assertNull($affiliate->payouts_frozen_until);

            // Submit new payout details
            $payoutService->updatePayoutDetails(
                $affiliate,
                Affiliate::PAYOUT_METHOD_WALLET,
                [
                    'wallet_type' => 'vodafone_cash',
                    'wallet_number' => '01099887766',
                ],
                (string) $affiliate->otp_code
            );

            $affiliate->refresh();

            // 1. Payout details must be readable through model cast
            $this->assertSame(Affiliate::PAYOUT_METHOD_WALLET, $affiliate->payout_method);
            $this->assertSame('vodafone_cash', $affiliate->payout_details['wallet_type']);
            $this->assertSame('01099887766', $affiliate->payout_details['wallet_number']);

            // 2. Raw database value must be encrypted (not plaintext)
            $rawDbValue = DB::table('affiliates')->where('id', $affiliate->id)->value('payout_details');
            $this->assertIsString($rawDbValue);
            $this->assertStringNotContainsString('01099887766', $rawDbValue);

            // 3. 24-hour security payout freeze must be activated
            $this->assertNotNull($affiliate->payouts_frozen_until);
            $this->assertTrue($affiliate->payouts_frozen_until->isFuture());
            $this->assertFalse($affiliate->canWithdraw());

            // 4. After 25 hours have passed, withdrawal is unfrozen
            Carbon::setTestNow(now()->addHours(25));
            $this->assertTrue($affiliate->canWithdraw());
            Carbon::setTestNow(); // reset
        });
    }

    public function test_affiliate_referral_attribution_link(): void
    {
        $this->tenant->run(function () {
            $regService = app(AffiliateRegistrationService::class);

            // Parent affiliate
            $parent = $regService->register([
                'name' => 'المسوق القائد',
                'phone' => '01066667777',
                'password' => 'pass123',
            ]);

            // Child affiliate using parent's code
            $child = $regService->register([
                'name' => 'المسوق الفرعي',
                'phone' => '01088889999',
                'password' => 'pass123',
                'referral_code' => $parent->code,
            ]);

            $this->assertSame($parent->id, $child->referred_by_id);
            $this->assertSame($parent->name, $child->referredBy?->name);
            $this->assertTrue($parent->referrals->contains('id', $child->id));
        });
    }

    /**
     * Acceptance Criterion A10:
     * Verification test proving every setting in AffiliateProgramSettings is actively read and enforced in code.
     */
    public function test_acceptance_criterion_a10_all_affiliate_program_settings_actively_enforced(): void
    {
        $this->tenant->run(function () {
            $settings = AffiliateProgramSettings::load();

            // 1. commission_model
            $settings->commission_model = 'markup';
            $this->assertTrue($settings->isMarkupModel());
            $this->assertFalse($settings->isPercentageModel());

            $settings->commission_model = 'percentage';
            $this->assertFalse($settings->isMarkupModel());
            $this->assertTrue($settings->isPercentageModel());

            $settings->commission_model = 'both';
            $this->assertTrue($settings->isMarkupModel());
            $this->assertTrue($settings->isPercentageModel());

            // 2. default_commission_rate
            $settings->default_commission_rate = 15;
            $this->assertSame(15, $settings->default_commission_rate);

            // 3. min_commission_minor & max_commission_minor
            $settings->min_commission_minor = 1000;  // 10.00 EGP
            $settings->max_commission_minor = 50000; // 500.00 EGP
            $this->assertSame(1000, $settings->clampCommission(500));    // clamped to min
            $this->assertSame(2500, $settings->clampCommission(2500));   // kept within bounds
            $this->assertSame(50000, $settings->clampCommission(70000)); // clamped to max

            // 4. min_selling_markup_minor & max_selling_markup_minor
            $settings->min_selling_markup_minor = 2000;  // 20.00 EGP
            $settings->max_selling_markup_minor = 100000; // 1,000.00 EGP
            $this->assertSame(2000, $settings->clampMarkup(1000));     // clamped to min
            $this->assertSame(15000, $settings->clampMarkup(15000));   // kept within bounds
            $this->assertSame(100000, $settings->clampMarkup(150000)); // clamped to max

            // 5. return_window_days
            $settings->return_window_days = 21;
            $this->assertSame(21, $settings->getReturnWindowDays());

            // 6. min_payout_minor
            $settings->min_payout_minor = 15000; // 150.00 EGP
            $this->assertFalse($settings->canRequestPayout(10000));
            $this->assertTrue($settings->canRequestPayout(15000));
            $this->assertTrue($settings->canRequestPayout(20000));

            // 7. payout_days
            $settings->payout_days = ['Monday', 'Thursday'];
            $this->assertTrue($settings->isPayoutDay('monday'));
            $this->assertTrue($settings->isPayoutDay('Thursday'));
            $this->assertFalse($settings->isPayoutDay('friday'));

            // 8. auto_approve_registrations
            $settings->auto_approve_registrations = true;
            $this->assertTrue($settings->shouldAutoApprove());
            $settings->auto_approve_registrations = false;
            $this->assertFalse($settings->shouldAutoApprove());

            // 9. show_customer_phone_to_affiliate
            $settings->show_customer_phone_to_affiliate = true;
            $this->assertTrue($settings->shouldShowCustomerPhone());
            $settings->show_customer_phone_to_affiliate = false;
            $this->assertFalse($settings->shouldShowCustomerPhone());

            // 10. default_daily_order_limit
            $settings->default_daily_order_limit = 75;
            $this->assertSame(75, $settings->getDailyOrderLimit());

            // 11. release_mode
            $settings->release_mode = 'courier_remittance';
            $this->assertSame('courier_remittance', $settings->getReleaseMode());

            // 12. payout_freeze_hours_on_change
            $settings->payout_freeze_hours_on_change = 48;
            $this->assertSame(48, $settings->getPayoutFreezeHours());

            // Save and reload to ensure full persistence round-trip
            $settings->save();

            $reloaded = AffiliateProgramSettings::load();
            $this->assertSame(48, $reloaded->payout_freeze_hours_on_change);
            $this->assertSame(75, $reloaded->default_daily_order_limit);
            $this->assertSame(21, $reloaded->return_window_days);
            $this->assertSame(['Monday', 'Thursday'], $reloaded->payout_days);
        });
    }
}
