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

    protected ?string $heading = 'أداء المتجر — آخر 30 يومًا';

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
            Stat::make('إجمالي الطلبات', (string) $int('total_orders'))
                ->description($int('pending_confirmation_orders').' بانتظار التأكيد')
                ->descriptionIcon('heroicon-m-phone')
                ->color($int('pending_confirmation_orders') > 0 ? 'warning' : 'success'),
            Stat::make('الطلبات المسلّمة', (string) $int('delivered_orders'))
                ->description('نسبة التسليم '.$rate('delivery_rate'))
                ->descriptionIcon('heroicon-m-truck')
                ->color('success'),
            Stat::make('المرتجعات والإلغاء', (string) ($int('returned_orders') + $int('cancelled_orders')))
                ->description('نسبة المرتجع '.$rate('return_rate'))
                ->descriptionIcon('heroicon-m-arrow-uturn-left')
                ->color('danger'),
            Stat::make('إجمالي المبيعات', $money('gross_revenue_minor'))
                ->description('صافي هامشك '.$money('net_merchant_margin_minor'))
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('primary'),
            Stat::make('عمولات المسوقين', $money('affiliate_commission_minor'))
                ->descriptionIcon('heroicon-m-user-group'),
            Stat::make('تنبيهات المخزون', (string) ($int('stockout_count') + $int('low_stock_count')))
                ->description($int('stockout_count').' نفد · '.$int('low_stock_count').' منخفض')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($int('stockout_count') > 0 ? 'danger' : 'gray'),
        ];
    }
}
