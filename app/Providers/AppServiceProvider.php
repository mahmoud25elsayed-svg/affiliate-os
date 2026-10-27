<?php

namespace App\Providers;

use App\Models\WholesalePrice;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Lunar\Admin\Support\Facades\LunarPanel;
use Lunar\Models\ProductVariant;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        LunarPanel::register();
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
    }
}
