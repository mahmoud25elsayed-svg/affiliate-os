<?php

declare(strict_types=1);

namespace Tests\Feature\Reports;

use App\Domain\Reports\Services\PlatformAnalyticsService;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlatformAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    protected User $platformAdmin;

    protected User $regularUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PlanSeeder::class);

        $this->platformAdmin = User::create([
            'name' => 'Platform Superadmin',
            'email' => 'superadmin@affiliate-os.com',
            'password' => bcrypt('SecretAdminPass!'),
            'is_platform_admin' => true,
        ]);

        $this->regularUser = User::create([
            'name' => 'Store Merchant Staff',
            'email' => 'staff@merchant.com',
            'password' => bcrypt('SecretStaffPass!'),
            'is_platform_admin' => false,
        ]);
    }

    public function test_platform_analytics_calculates_active_merchants_and_mrr_and_churn(): void
    {
        /** @var Plan $starterPlan */
        $starterPlan = Plan::where('slug', 'starter')->firstOrFail();
        /** @var Plan $proPlan */
        $proPlan = Plan::where('slug', 'pro')->firstOrFail();

        // 1. Tenant 1: Monthly Pro Plan (e.g. 150000 minor = 1,500 EGP/mo)
        $tenant1 = Tenant::create(['id' => 't-active-1', 'name' => 'Active Store 1', 'status' => 'active']);
        $tenant1->subscriptions()->create([
            'plan_id' => $proPlan->id,
            'status' => Subscription::STATUS_ACTIVE,
            'billing_cycle' => Subscription::CYCLE_MONTHLY,
            'current_period_starts_at' => now(),
            'current_period_ends_at' => now()->addMonth(),
        ]);

        // 2. Tenant 2: Yearly Starter Plan (e.g. price_yearly = 600000 minor -> 50000 minor/mo)
        $tenant2 = Tenant::create(['id' => 't-active-2', 'name' => 'Active Store 2', 'status' => 'active']);
        $tenant2->subscriptions()->create([
            'plan_id' => $starterPlan->id,
            'status' => Subscription::STATUS_ACTIVE,
            'billing_cycle' => Subscription::CYCLE_YEARLY,
            'current_period_starts_at' => now(),
            'current_period_ends_at' => now()->addYear(),
        ]);

        // 3. Tenant 3: Cancelled subscription in the past 10 days
        $tenant3 = Tenant::create(['id' => 't-cancelled-3', 'name' => 'Cancelled Store 3', 'status' => 'inactive']);
        $tenant3->subscriptions()->create([
            'plan_id' => $starterPlan->id,
            'status' => Subscription::STATUS_CANCELLED,
            'billing_cycle' => Subscription::CYCLE_MONTHLY,
            'cancelled_at' => Carbon::now()->subDays(10),
        ]);

        /** @var PlatformAnalyticsService $platformService */
        $platformService = app(PlatformAnalyticsService::class);

        // Active merchants: tenant 1 and 2 (or active status)
        $activeCount = $platformService->getActiveMerchantsCount();
        $this->assertGreaterThanOrEqual(2, $activeCount);

        // Expected MRR: Pro monthly + Starter yearly/12
        $expectedMrr = (int) $proPlan->price_monthly + (int) round($starterPlan->price_yearly / 12);
        $actualMrr = $platformService->getMonthlyRecurringRevenueMinor();
        $this->assertSame($expectedMrr, $actualMrr);

        // Churn rate: 1 cancelled out of 3 total in past 30 days = 33.3%
        $churnRate = $platformService->getChurnRate();
        $this->assertEqualsWithDelta(33.3, $churnRate, 0.5);

        // Plan Usage Breakdown
        $breakdown = $platformService->getPlanUsageBreakdown();
        $this->assertArrayHasKey('starter', $breakdown);
        $this->assertArrayHasKey('pro', $breakdown);
        $this->assertSame(2, $breakdown['starter']['count']); // tenant2 + tenant3
        $this->assertSame(1, $breakdown['pro']['count']); // tenant1
    }

    public function test_tenant_errors_can_be_recorded_and_summarized(): void
    {
        /** @var PlatformAnalyticsService $platformService */
        $platformService = app(PlatformAnalyticsService::class);

        $tenantA = Tenant::create(['id' => 'tenant-err-a', 'name' => 'Store With Errors A', 'status' => 'active']);
        $tenantB = Tenant::create(['id' => 'tenant-err-b', 'name' => 'Store With Errors B', 'status' => 'active']);

        // Record 2 errors for Tenant A
        $platformService->recordTenantError('tenant-err-a', 'Payment gateway webhook failed', 'critical', ['gateway' => 'fawry']);
        $platformService->recordTenantError('tenant-err-a', 'Inventory sync mismatch', 'warning');

        // Record 1 error for Tenant B
        $platformService->recordTenantError('tenant-err-b', 'Courier API timeout', 'error', ['courier' => 'bosta']);

        $summary = $platformService->getTenantErrorsSummary();
        $this->assertNotEmpty($summary);

        $rowA = $summary->firstWhere('tenant_id', 'tenant-err-a');
        $this->assertNotNull($rowA);
        $this->assertSame(2, $rowA['error_count']);
        $this->assertSame('Store With Errors A', $rowA['tenant_name']);

        $recentLogs = $platformService->getRecentErrorLogs(10);
        $this->assertCount(3, $recentLogs);
        $this->assertSame('bosta', $recentLogs->firstWhere('tenant_id', 'tenant-err-b')?->context['courier'] ?? null);
    }

    public function test_platform_admin_access_control(): void
    {
        // 1. Regular user is forbidden (403)
        $unauthorizedResponse = $this->actingAs($this->regularUser, 'web')
            ->get(route('platform.analytics.index'));
        $unauthorizedResponse->assertForbidden();

        // 2. Platform superadmin has full access (200)
        $adminResponse = $this->actingAs($this->platformAdmin, 'web')
            ->get(route('platform.analytics.index'));
        $adminResponse->assertOk();
        $adminResponse->assertViewIs('platform.analytics.index');
        $adminResponse->assertSee('تحليلات المنصة المركزية ومؤشرات الـ SaaS');
        $adminResponse->assertSee('الإيراد الشهري المتكرر (MRR)');
        $adminResponse->assertSee('معدل الانقطاع (Churn Rate)');
    }
}
