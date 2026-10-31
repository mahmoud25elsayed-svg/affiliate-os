<?php

declare(strict_types=1);

namespace App\Filament\Store\Widgets;

use App\Domain\Operations\Services\StoreKpiMetricsService;
use App\Support\Money;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StoreOverviewStats extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected function getHeading(): ?string
    {
        return __('أداء المتجر — آخر 30 يومًا');
    }

    /**
     * @return array<Stat>
     */
    protected function getStats(): array
    {
        $m = app(StoreKpiMetricsService::class)->getMetrics('month');

        $int = static fn (string $key): int => is_numeric($m[$key] ?? null) ? (int) $m[$key] : 0;
        $money = static fn (string $key): string => Money::fromMinor($int($key))->format();
        $rate = static fn (string $key): string => (is_numeric($m[$key] ?? null) ? round((float) $m[$key], 1) : 0).'%';

        return [
            Stat::make(__('إجمالي الطلبات'), (string) $int('total_orders'))
                ->description($int('pending_confirmation_orders').__(' بانتظار التأكيد'))
                ->descriptionIcon('heroicon-m-phone')
                ->color($int('pending_confirmation_orders') > 0 ? 'warning' : 'success'),
            Stat::make(__('الطلبات المسلّمة'), (string) $int('delivered_orders'))
                ->description(__('نسبة التسليم ').$rate('delivery_rate'))
                ->descriptionIcon('heroicon-m-truck')
                ->color('success'),
            Stat::make(__('المرتجعات والإلغاء'), (string) ($int('returned_orders') + $int('cancelled_orders')))
                ->description(__('نسبة المرتجع ').$rate('return_rate'))
                ->descriptionIcon('heroicon-m-arrow-uturn-left')
                ->color('danger'),
            Stat::make(__('إجمالي المبيعات'), $money('gross_revenue_minor'))
                ->description(__('صافي هامشك ').$money('net_merchant_margin_minor'))
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('primary'),
            Stat::make(__('عمولات المسوقين'), $money('affiliate_commission_minor'))
                ->descriptionIcon('heroicon-m-user-group'),
            Stat::make(__('تنبيهات المخزون'), (string) ($int('stockout_count') + $int('low_stock_count')))
                ->description($int('stockout_count').__(' نفد · ').$int('low_stock_count').__(' منخفض'))
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($int('stockout_count') > 0 ? 'danger' : 'gray'),
        ];
    }
}
