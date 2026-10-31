<?php

declare(strict_types=1);

namespace App\Http\Controllers\Platform;

use App\Domain\Reports\Services\PlatformAnalyticsService;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PlatformAnalyticsController extends Controller
{
    public function __construct(
        protected PlatformAnalyticsService $platformAnalytics
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        if (! ($user instanceof User) || ! $user->isPlatformAdmin()) {
            abort(403, __('غير مصرح بالوصول إلى تحليلات المنصة المركزية'));
        }

        $activeMerchants = $this->platformAnalytics->getActiveMerchantsCount();
        $mrrMinor = $this->platformAnalytics->getMonthlyRecurringRevenueMinor();
        $churnRate = $this->platformAnalytics->getChurnRate();
        $planBreakdown = $this->platformAnalytics->getPlanUsageBreakdown();
        $tenantErrors = $this->platformAnalytics->getTenantErrorsSummary(15);
        $recentLogs = $this->platformAnalytics->getRecentErrorLogs(25);

        return view('platform.analytics.index', [
            'activeMerchants' => $activeMerchants,
            'mrrMinor' => $mrrMinor,
            'churnRate' => $churnRate,
            'planBreakdown' => $planBreakdown,
            'tenantErrors' => $tenantErrors,
            'recentLogs' => $recentLogs,
        ]);
    }
}
