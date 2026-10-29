<?php

declare(strict_types=1);

namespace App\Domain\Reports\Services;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\TenantErrorLog;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PlatformAnalyticsService
{
    /**
     * Total count of active merchants/tenants.
     */
    public function getActiveMerchantsCount(): int
    {
        return Tenant::query()
            ->whereHas('subscriptions', function ($query) {
                $query->whereIn('status', [Subscription::STATUS_ACTIVE, Subscription::STATUS_TRIAL]);
            })
            ->orWhere('status', 'active')
            ->count();
    }

    /**
     * Compute Monthly Recurring Revenue (MRR) in minor currency units (EGP cents).
     */
    public function getMonthlyRecurringRevenueMinor(): int
    {
        $subscriptions = Subscription::with('plan')
            ->whereIn('status', [Subscription::STATUS_ACTIVE, Subscription::STATUS_TRIAL])
            ->get();

        $totalMrrMinor = 0;

        foreach ($subscriptions as $sub) {
            $plan = $sub->plan;

            if ($sub->billing_cycle === Subscription::CYCLE_YEARLY) {
                $totalMrrMinor += (int) round($plan->price_yearly / 12);
            } else {
                $totalMrrMinor += (int) $plan->price_monthly;
            }
        }

        return $totalMrrMinor;
    }

    /**
     * Compute platform churn rate percentage over the past 30 days.
     */
    public function getChurnRate(): float
    {
        $thirtyDaysAgo = Carbon::now()->subDays(30);

        $cancelledInPeriod = Subscription::query()
            ->where('status', Subscription::STATUS_CANCELLED)
            ->where('cancelled_at', '>=', $thirtyDaysAgo)
            ->count();

        $activeOrCancelled = Subscription::query()
            ->where(function ($q) use ($thirtyDaysAgo) {
                $q->whereIn('status', [Subscription::STATUS_ACTIVE, Subscription::STATUS_TRIAL])
                    ->orWhere(function ($q2) use ($thirtyDaysAgo) {
                        $q2->where('status', Subscription::STATUS_CANCELLED)
                            ->where('cancelled_at', '>=', $thirtyDaysAgo);
                    });
            })
            ->count();

        if ($activeOrCancelled <= 0) {
            return 0.0;
        }

        return round(($cancelledInPeriod / $activeOrCancelled) * 100, 1);
    }

    /**
     * Get distribution and usage breakdown across subscription plans.
     *
     * @return array<string, array{name: string, count: int, percentage: float, mrr_minor: int}>
     */
    public function getPlanUsageBreakdown(): array
    {
        $plans = Plan::all();
        $subscriptions = Subscription::with('plan')->get();
        $totalSubs = max(1, $subscriptions->count());

        $breakdown = [];

        foreach ($plans as $plan) {
            $planSubs = $subscriptions->where('plan_id', $plan->id);
            $count = $planSubs->count();

            $planMrr = 0;
            foreach ($planSubs->whereIn('status', [Subscription::STATUS_ACTIVE, Subscription::STATUS_TRIAL]) as $sub) {
                $planMrr += $sub->billing_cycle === Subscription::CYCLE_YEARLY
                    ? (int) round($plan->price_yearly / 12)
                    : (int) $plan->price_monthly;
            }

            $breakdown[$plan->slug] = [
                'name' => $plan->name,
                'count' => $count,
                'percentage' => round(($count / $totalSubs) * 100, 1),
                'mrr_minor' => $planMrr,
            ];
        }

        return $breakdown;
    }

    /**
     * Record a runtime error or exception scoped to a tenant.
     *
     * @param  array<string, mixed>  $context
     */
    public function recordTenantError(
        ?string $tenantId,
        string $message,
        string $level = 'error',
        array $context = [],
        ?string $exceptionClass = null
    ): TenantErrorLog {
        return TenantErrorLog::create([
            'tenant_id' => $tenantId,
            'level' => $level,
            'message' => $message,
            'context' => $context,
            'exception_class' => $exceptionClass,
            'created_at' => Carbon::now(),
        ]);
    }

    /**
     * Get aggregated error count per tenant.
     *
     * @return Collection<int, array{tenant_id: string|null, tenant_name: string, error_count: int, last_error_at: Carbon}>
     */
    public function getTenantErrorsSummary(int $limit = 20): Collection
    {
        $results = DB::table('tenant_error_logs')
            ->select('tenant_id', DB::raw('count(*) as error_count'), DB::raw('max(created_at) as last_error_at'))
            ->groupBy('tenant_id')
            ->orderByDesc('error_count')
            ->take($limit)
            ->get();

        $tenants = Tenant::whereIn('id', $results->pluck('tenant_id')->filter())->get()->keyBy('id');

        return $results->map(function (object $row) use ($tenants) {
            /** @var Tenant|null $tenant */
            $tenant = isset($row->tenant_id) && $row->tenant_id ? $tenants->get((string) $row->tenant_id) : null;
            $tenantId = isset($row->tenant_id) ? (string) $row->tenant_id : null;
            $lastErrorAt = isset($row->last_error_at) ? (string) $row->last_error_at : 'now';

            return [
                'tenant_id' => $tenantId,
                'tenant_name' => (string) ($tenant ? $tenant->name : ($tenantId ?: 'النظام المركزي')),
                'error_count' => (int) ($row->error_count ?? 0),
                'last_error_at' => Carbon::parse($lastErrorAt),
            ];
        });
    }

    /**
     * Get recent detailed tenant error logs.
     *
     * @return Collection<int, TenantErrorLog>
     */
    public function getRecentErrorLogs(int $limit = 50, ?string $tenantId = null): Collection
    {
        $query = TenantErrorLog::with('tenant')->latest('created_at');

        if ($tenantId !== null) {
            $query->where('tenant_id', $tenantId);
        }

        return $query->take($limit)->get();
    }
}
