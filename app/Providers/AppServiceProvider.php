<?php

namespace App\Providers;

use App\Domain\AI\Contracts\AIProvider;
use App\Domain\AI\Providers\ClaudeAiProvider;
use App\Domain\AI\Providers\FakeAiProvider;
use App\Domain\Customer\Contracts\CustomerWalletServiceInterface;
use App\Domain\Customer\Services\CustomerWalletService;
use App\Domain\Orders\Events\OrderDelivered;
use App\Domain\Orders\Exceptions\IllegalOrderStateMutationException;
use App\Domain\Orders\Services\OrderTransitionService;
use App\Domain\Storefront\Services\StorefrontCacheService;
use App\Domain\Tenancy\Contracts\DnsResolverInterface;
use App\Domain\Tenancy\Contracts\HostingerClientInterface;
use App\Domain\Tenancy\Services\HostingerDomainService;
use App\Domain\Tenancy\Services\SystemDnsResolver;
use App\Domain\Tracking\Listeners\TrackOrderDeliveredConversionListener;
use App\Http\Middleware\AbortUnlessLunarHubEnabled;
use App\Http\Middleware\InitializeTenancyForLivewire;
use App\Models\Affiliate;
use App\Models\TrackingLink;
use App\Models\WholesalePrice;
use Filament\Panel;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use Lunar\Admin\Support\Facades\LunarPanel;
use Lunar\Models\Collection;
use Lunar\Models\Order;
use Lunar\Models\Product;
use Lunar\Models\ProductVariant;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Lunar's internals need its panel registered, but the stock hub is not tenant-aware (its own
        // "staff" guard sees every store's data) and merchants use the Store panel instead. Its routes
        // therefore 404 unless explicitly enabled, e.g. for local debugging.
        LunarPanel::panel(fn (Panel $panel) => $panel->middleware([AbortUnlessLunarHubEnabled::class], isPersistent: true))
            ->register();

        $this->app->singleton(
            CustomerWalletServiceInterface::class,
            CustomerWalletService::class
        );

        $this->app->singleton(
            DnsResolverInterface::class,
            SystemDnsResolver::class
        );

        $this->app->singleton(
            HostingerClientInterface::class,
            HostingerDomainService::class
        );

        $this->app->singleton(
            AIProvider::class,
            function () {
                if (app()->environment('testing')) {
                    return new FakeAiProvider;
                }

                return new ClaudeAiProvider;
            }
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Storefront Livewire components need the tenant of the domain they were rendered on.
        Livewire::setUpdateRoute(fn ($handle) => Route::post('/livewire/update', $handle)
            ->middleware(['web', InitializeTenancyForLivewire::class]));

        RateLimiter::for('merchant-registration', function (Request $request) {
            return Limit::perHour(5)->by($request->ip() ?? '127.0.0.1');
        });

        RateLimiter::for('otp-request', function (Request $request) {
            return Limit::perMinute(3)->by((string) ($request->input('phone') ?? $request->ip() ?? '127.0.0.1'));
        });

        RateLimiter::for('merchant-login', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip() ?? '127.0.0.1');
        });

        ProductVariant::resolveRelationUsing('wholesalePrice', function (ProductVariant $variant) {
            return $variant->hasOne(WholesalePrice::class, 'product_variant_id');
        });

        Order::resolveRelationUsing('affiliate', function (Order $order) {
            return $order->belongsTo(Affiliate::class, 'affiliate_id');
        });

        Order::resolveRelationUsing('trackingLink', function (Order $order) {
            return $order->belongsTo(TrackingLink::class, 'tracking_link_id');
        });

        Event::listen(
            OrderDelivered::class,
            TrackOrderDeliveredConversionListener::class
        );

        // Storefront cache invalidation hooks
        Product::saved(fn () => StorefrontCacheService::flush());
        Product::deleted(fn () => StorefrontCacheService::flush());
        Collection::saved(fn () => StorefrontCacheService::flush());
        Collection::deleted(fn () => StorefrontCacheService::flush());

        // Golden rule: direct order status updates outside OrderTransitionService are strictly prohibited
        Order::updating(function (Order $order): void {
            if ($order->isDirty('status') && ! OrderTransitionService::$isTransitioning) {
                throw new IllegalOrderStateMutationException;
            }
        });
    }
}
