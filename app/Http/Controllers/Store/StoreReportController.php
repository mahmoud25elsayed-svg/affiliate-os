<?php

declare(strict_types=1);

namespace App\Http\Controllers\Store;

use App\Domain\Reports\Services\MerchantAnalyticsService;
use App\Domain\Reports\Services\ReportExportService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StoreReportController extends Controller
{
    public function __construct(
        protected MerchantAnalyticsService $analyticsService,
        protected ReportExportService $exportService
    ) {}

    public function index(Request $request): View
    {
        $preset = (string) $request->input('preset', 'last_30_days');
        if (! in_array($preset, ['today', 'yesterday', 'last_7_days', 'last_30_days', 'this_month', 'last_month', 'custom'], true)) {
            $preset = 'last_30_days';
        }

        $from = $request->input('from') ? (string) $request->input('from') : null;
        $to = $request->input('to') ? (string) $request->input('to') : null;
        $activeTab = (string) $request->input('tab', 'overview');

        $overview = $this->analyticsService->getOverview($preset, $from, $to);
        $topProducts = $this->analyticsService->getTopProducts($preset, 15, $from, $to);
        $topAffiliates = $this->analyticsService->getTopAffiliates($preset, 15, $from, $to);
        $governorates = $this->analyticsService->getGovernoratesBreakdown($preset, $from, $to);
        $returnsBreakdown = $this->analyticsService->getReturnsBreakdown($preset, $from, $to);
        $inventoryReport = $this->analyticsService->getInventoryReport();
        $taxReport = $this->analyticsService->getTaxReport($preset, $from, $to);

        return view('store.reports.index', [
            'preset' => $preset,
            'from' => $overview['from'],
            'to' => $overview['to'],
            'activeTab' => $activeTab,
            'overview' => $overview,
            'topProducts' => $topProducts,
            'topAffiliates' => $topAffiliates,
            'governorates' => $governorates,
            'returnsBreakdown' => $returnsBreakdown,
            'inventoryReport' => $inventoryReport,
            'taxReport' => $taxReport,
        ]);
    }

    public function export(Request $request, string $type): StreamedResponse
    {
        $allowedTypes = ['sales', 'products', 'affiliates', 'governorates', 'returns'];
        if (! in_array($type, $allowedTypes, true)) {
            abort(404, __('نوع التقرير غير مدعوم'));
        }

        $preset = (string) $request->input('preset', 'last_30_days');
        $from = $request->input('from') ? (string) $request->input('from') : null;
        $to = $request->input('to') ? (string) $request->input('to') : null;

        return $this->exportService->exportCsv($type, $preset, $from, $to);
    }
}
