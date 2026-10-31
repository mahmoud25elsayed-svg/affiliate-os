<?php

namespace App\Providers\Filament;

use App\Http\Middleware\InitializeTenancyForFilament;
use App\Models\Tenant;
use App\Support\Billing\Features;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationItem;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Filament\Widgets\AccountWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Blade;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class StorePanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('store')
            ->path('store')
            ->brandName('AffiliateOS Merchant Store')
            ->login()
            ->tenant(Tenant::class, slugAttribute: 'id')
            ->tenantMiddleware([
                InitializeTenancyForFilament::class,
            ], isPersistent: true)
            ->colors([
                'primary' => Color::Emerald,
            ])
            ->discoverResources(in: app_path('Filament/Store/Resources'), for: 'App\Filament\Store\Resources')
            ->discoverPages(in: app_path('Filament/Store/Pages'), for: 'App\Filament\Store\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn (): string => Blade::render("@vite('resources/css/filament-panels.css')")
            )
            ->renderHook(
                PanelsRenderHook::BODY_START,
                fn () => view('filament.store.components.impersonation-banner')
            )
            ->navigationItems($this->operationalPages())
            ->discoverWidgets(in: app_path('Filament/Store/Widgets'), for: 'App\Filament\Store\Widgets')
            ->widgets([
                AccountWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }

    /**
     * Merchant tools built as standalone pages outside Filament (P20, P23–P33). Without these
     * entries the merchant had no way to reach them from the panel.
     *
     * @return list<NavigationItem>
     */
    protected function operationalPages(): array
    {
        // [label, route, icon, group, sort, plan feature or null]
        $pages = [
            ['مركز الاتصال والتأكيد', 'store.call-center.index', 'heroicon-o-phone', 'إدارة الطلبات', 2, 'call_center'],
            ['أداء الكول سنتر', 'store.call-center.performance', 'heroicon-o-chart-bar-square', 'إدارة الطلبات', 3, 'call_center'],
            ['الشحنات وشركات الشحن', 'store.shipments.index', 'heroicon-o-truck', 'إدارة الطلبات', 4, 'courier'],
            ['تسويات التحصيل COD', 'store.cod.index', 'heroicon-o-banknotes', 'المالية', 1, 'cod_reconciliation'],
            ['طلبات سحب المسوقين', 'store.payouts.index', 'heroicon-o-arrow-up-tray', 'المالية', 2, null],
            ['محافظ العملاء والولاء', 'store.wallets.index', 'heroicon-o-wallet', 'المالية', 3, 'loyalty'],
            ['مؤشرات الأداء KPIs', 'store.kpis.index', 'heroicon-o-presentation-chart-line', 'التقارير', 1, null],
            ['التقارير والتحليلات', 'store.reports.index', 'heroicon-o-document-chart-bar', 'التقارير', 2, null],
            ['الموردون والدفعات', 'store.suppliers.index', 'heroicon-o-building-storefront', 'المخزون والمستودعات', 3, null],
            ['مصمم الواجهة', 'store.theme.edit', 'heroicon-o-paint-brush', 'المتجر والعملاء', 1, null],
            ['النطاقات المخصصة', 'store.domains.index', 'heroicon-o-globe-alt', 'المتجر والعملاء', 2, 'custom_domain'],
            ['البكسل والتتبع الإعلاني', 'store.pixels.index', 'heroicon-o-cursor-arrow-rays', 'التسويق والمبيعات', 9, null],
            ['أدوات الذكاء الاصطناعي', 'store.ai.index', 'heroicon-o-sparkles', 'الأدوات والدعم', 1, 'ai'],
            ['الدعم والتذاكر', 'store.support.index', 'heroicon-o-lifebuoy', 'الأدوات والدعم', 2, null],
        ];

        return array_map(
            fn (array $p): NavigationItem => NavigationItem::make($p[0])
                ->url(fn (): string => route($p[1]))
                ->isActiveWhen(fn (): bool => request()->routeIs($p[1].'*'))
                ->icon($p[2])
                ->group($p[3])
                ->sort($p[4])
                ->visible(fn (): bool => $p[5] === null || Features::enabled($p[5])),
            $pages,
        );
    }
}
