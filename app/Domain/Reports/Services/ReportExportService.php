<?php

declare(strict_types=1);

namespace App\Domain\Reports\Services;

use App\Support\Money;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportExportService
{
    public function __construct(
        protected MerchantAnalyticsService $analyticsService
    ) {}

    /**
     * Generate streamed CSV response with UTF-8 BOM for Microsoft Excel.
     */
    public function exportCsv(string $type, string $preset = 'last_30_days', ?string $from = null, ?string $to = null): StreamedResponse
    {
        $filename = "report-{$type}-{$preset}-".date('YmdHis').'.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        return response()->stream(function () use ($type, $preset, $from, $to) {
            $handle = fopen('php://output', 'w');
            if (! $handle) {
                return;
            }

            // Write UTF-8 BOM for Arabic support in Excel
            fwrite($handle, "\xEF\xBB\xBF");

            match ($type) {
                'sales' => $this->exportSales($handle, $preset, $from, $to),
                'products' => $this->exportProducts($handle, $preset, $from, $to),
                'affiliates' => $this->exportAffiliates($handle, $preset, $from, $to),
                'governorates' => $this->exportGovernorates($handle, $preset, $from, $to),
                'returns' => $this->exportReturns($handle, $preset, $from, $to),
                default => $this->exportSales($handle, $preset, $from, $to),
            };

            fclose($handle);
        }, 200, $headers);
    }

    /**
     * @param  resource  $handle
     */
    protected function exportSales($handle, string $preset, ?string $from, ?string $to): void
    {
        fputcsv($handle, [
            __('التاريخ'),
            __('إجمالي الطلبات'),
            __('الطلبات المؤكدة'),
            __('الطلبات المسلّمة'),
            __('الطلبات المرتجعة'),
            __('الطلبات الملغاة'),
            __('إجمالي المبيعات (').Money::symbol().')',
            __('عمولات المسوقين (').Money::symbol().')',
            __('مصاريف الشحن (').Money::symbol().')',
            __('تكلفة البضاعة المباعة (').Money::symbol().')',
            __('الضرائب (').Money::symbol().')',
            __('صافي الربح (').Money::symbol().')',
            __('نسبة التسليم (%)'),
            __('نسبة المرتجعات (%)'),
        ]);

        $overview = $this->analyticsService->getOverview($preset, $from, $to);
        $records = $overview['daily_records'] ?? [];

        foreach ($records as $metric) {
            fputcsv($handle, [
                $metric->metric_date->toDateString(),
                $metric->orders_count,
                $metric->confirmed_orders_count,
                $metric->delivered_orders_count,
                $metric->returned_orders_count,
                $metric->cancelled_orders_count,
                Money::fromMinor($metric->gross_revenue_minor)->toDecimalString(),
                Money::fromMinor($metric->affiliate_commission_minor)->toDecimalString(),
                Money::fromMinor($metric->shipping_expense_minor)->toDecimalString(),
                Money::fromMinor($metric->cogs_minor)->toDecimalString(),
                Money::fromMinor($metric->tax_minor)->toDecimalString(),
                Money::fromMinor($metric->net_profit_minor)->toDecimalString(),
                $metric->deliveryRate(),
                $metric->returnRate(),
            ]);
        }
    }

    /**
     * @param  resource  $handle
     */
    protected function exportProducts($handle, string $preset, ?string $from, ?string $to): void
    {
        fputcsv($handle, [
            __('اسم المنتج'),
            __('رمز المنتج (SKU)'),
            __('إجمالي القطع المطلوبة'),
            __('القطع المسلّمة'),
            __('القطع المرتجعة'),
            __('إجمالي المبيعات (').Money::symbol().')',
            __('نسبة المرتجعات (%)'),
        ]);

        $products = $this->analyticsService->getTopProducts($preset, 100, $from, $to);
        foreach ($products as $prod) {
            fputcsv($handle, [
                $prod['name'],
                $prod['identifier'],
                $prod['total_units_ordered'],
                $prod['delivered_units'],
                $prod['returned_units'],
                Money::fromMinor((int) $prod['total_revenue_minor'])->toDecimalString(),
                $prod['return_rate'].'%',
            ]);
        }
    }

    /**
     * @param  resource  $handle
     */
    protected function exportAffiliates($handle, string $preset, ?string $from, ?string $to): void
    {
        fputcsv($handle, [
            __('اسم المسوق'),
            __('كود المسوق'),
            __('رقم الهاتف'),
            __('إجمالي الطلبات'),
            __('الطلبات المسلّمة'),
            __('نسبة التسليم (%)'),
            __('إجمالي مبيعات المسوق (').Money::symbol().')',
            __('إجمالي عمولات المسوق (').Money::symbol().')',
        ]);

        $affiliates = $this->analyticsService->getTopAffiliates($preset, 100, $from, $to);
        foreach ($affiliates as $aff) {
            fputcsv($handle, [
                $aff['name'],
                $aff['code'],
                $aff['phone'],
                $aff['orders_count'],
                $aff['delivered_count'],
                $aff['delivery_rate'].'%',
                Money::fromMinor((int) $aff['revenue_minor'])->toDecimalString(),
                Money::fromMinor((int) $aff['commission_minor'])->toDecimalString(),
            ]);
        }
    }

    /**
     * @param  resource  $handle
     */
    protected function exportGovernorates($handle, string $preset, ?string $from, ?string $to): void
    {
        fputcsv($handle, [
            __('المحافظة'),
            __('إجمالي الطلبات'),
            __('الطلبات المسلّمة'),
            __('الطلبات المرتجعة'),
            __('نسبة التسليم (%)'),
            __('نسبة المرتجعات (%)'),
            __('إجمالي المبيعات (').Money::symbol().')',
        ]);

        $govs = $this->analyticsService->getGovernoratesBreakdown($preset, $from, $to);
        foreach ($govs as $gov) {
            fputcsv($handle, [
                $gov['governorate'],
                $gov['orders_count'],
                $gov['delivered_count'],
                $gov['returned_count'],
                $gov['delivery_rate'].'%',
                $gov['return_rate'].'%',
                Money::fromMinor((int) $gov['revenue_minor'])->toDecimalString(),
            ]);
        }
    }

    /**
     * @param  resource  $handle
     */
    protected function exportReturns($handle, string $preset, ?string $from, ?string $to): void
    {
        fputcsv($handle, [
            __('سبب الإرجاع / الفشل'),
            __('عدد الحالات'),
            __('النسبة المئوية من إجمالي المرتجعات (%)'),
        ]);

        $report = $this->analyticsService->getReturnsBreakdown($preset, $from, $to);
        foreach ($report['reasons'] as $reason) {
            fputcsv($handle, [
                $reason['label'],
                $reason['count'],
                $reason['percentage'].'%',
            ]);
        }
    }
}
