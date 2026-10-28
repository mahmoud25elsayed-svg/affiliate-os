<?php

declare(strict_types=1);

namespace App\Http\Controllers\Store;

use App\Domain\Finance\Services\PayoutTurnaroundCalculator;
use App\Domain\Operations\Services\StoreKpiMetricsService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StoreKpiDashboardController extends Controller
{
    public function index(
        Request $request,
        StoreKpiMetricsService $metricsService,
        PayoutTurnaroundCalculator $turnaroundCalculator
    ): View {
        $period = (string) $request->input('period', 'all');
        if (! in_array($period, ['today', 'week', 'month', 'all'], true)) {
            $period = 'all';
        }

        $metrics = $metricsService->getMetrics($period);
        $averageTurnaround = $turnaroundCalculator->getAverageTurnaroundHuman();

        return view('store.kpis.dashboard', [
            'metrics' => $metrics,
            'currentPeriod' => $period,
            'averageTurnaround' => $averageTurnaround,
        ]);
    }
}
