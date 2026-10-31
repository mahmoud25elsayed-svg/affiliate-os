<?php

declare(strict_types=1);

namespace App\Filament\Platform\Widgets;

use App\Domain\Reports\Services\PlatformAnalyticsService;
use App\Models\SubscriptionInvoice;
use App\Models\Tenant;
use App\Support\Money;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class PlatformOverviewStats extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected function getHeading(): ?string
    {
        return __('نظرة عامة على المنصة');
    }

    /**
     * @return array<Stat>
     */
    protected function getStats(): array
    {
        $analytics = app(PlatformAnalyticsService::class);
        $pendingInvoices = SubscriptionInvoice::query()->where('status', SubscriptionInvoice::STATUS_PENDING)->count();

        return [
            Stat::make(__('المتاجر النشطة'), (string) $analytics->getActiveMerchantsCount())
                ->description(__('من إجمالي ').Tenant::query()->count().__(' متجر'))
                ->descriptionIcon('heroicon-m-building-storefront')
                ->color('success'),
            Stat::make(__('الإيراد الشهري المتكرر (MRR)'), Money::fromMinor($analytics->getMonthlyRecurringRevenueMinor())->format())
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('primary'),
            Stat::make(__('معدل الإلغاء (Churn)'), $analytics->getChurnRate().'%')
                ->descriptionIcon('heroicon-m-arrow-trending-down')
                ->color('warning'),
            Stat::make(__('فواتير بانتظار المراجعة'), (string) $pendingInvoices)
                ->description(__('تحويلات يدوية تحتاج اعتماد'))
                ->descriptionIcon('heroicon-m-document-check')
                ->color($pendingInvoices > 0 ? 'danger' : 'gray'),
        ];
    }
}
