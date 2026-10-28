<?php

namespace App\Providers;

use App\Domain\Customer\Contracts\CustomerWalletServiceInterface;
use App\Domain\Customer\Services\CustomerWalletService;
use App\Domain\Orders\Exceptions\IllegalOrderStateMutationException;
use App\Domain\Orders\Services\OrderTransitionService;
use App\Domain\Storefront\Services\StorefrontCacheService;
use App\Models\Affiliate;
use App\Models\WholesalePrice;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
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
        LunarPanel::register();

        $this->app->singleton(
            CustomerWalletServiceInterface::class,
            CustomerWalletService::class
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
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
