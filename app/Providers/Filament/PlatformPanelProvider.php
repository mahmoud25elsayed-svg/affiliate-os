<?php

namespace App\Providers\Filament;

use App\Http\Middleware\EnforcePlatformTwoFactor;
use App\Http\Middleware\SetLocale;
use Filament\Actions\Action;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationItem;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets\AccountWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class PlatformPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('platform')
            ->path('platform')
            ->brandName('AffiliateOS Central Platform')
            ->login()
            ->userMenuItems([
                // Relative link: reloads the current page in the other language (SetLocale stores it).
                Action::make('switch_language')
                    ->label(fn (): string => app()->getLocale() === 'ar' ? 'English' : __('العربية'))
                    ->icon('heroicon-o-language')
                    ->url(fn (): string => '?lang='.(app()->getLocale() === 'ar' ? 'en' : 'ar')),
            ])
            ->colors([
                'primary' => Color::Indigo,
            ])
            ->discoverResources(in: app_path('Filament/Platform/Resources'), for: 'App\Filament\Platform\Resources')
            ->discoverPages(in: app_path('Filament/Platform/Pages'), for: 'App\Filament\Platform\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Platform/Widgets'), for: 'App\Filament\Platform\Widgets')
            ->widgets([
                AccountWidget::class,
            ])
            ->navigationItems([
                // Platform analytics & error monitoring (P33) is a standalone page outside Filament.
                NavigationItem::make(fn (): string => __('التحليلات ومراقبة الأخطاء'))
                    ->url(fn (): string => route('platform.analytics.index'))
                    ->icon('heroicon-o-chart-pie')
                    ->sort(2),
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                SetLocale::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
                EnforcePlatformTwoFactor::class,
            ]);
    }
}
