<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Orders\Events\OrderDelivered;
use App\Domain\Promotions\Listeners\AwardLoyaltyPointsOnOrderDelivered;
use App\Domain\Promotions\Services\AbandonedCartService;
use App\Domain\Promotions\Services\LoyaltyPointsService;
use App\Domain\Promotions\Services\NewsletterService;
use App\Domain\Promotions\Services\PromotionPricingService;
use App\Models\DailyDeal;
use App\Models\FlashSaleItem;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Lunar\Models\ProductVariant;

class PromotionServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PromotionPricingService::class);
        $this->app->singleton(LoyaltyPointsService::class);
        $this->app->singleton(AbandonedCartService::class);
        $this->app->singleton(NewsletterService::class);
    }

    public function boot(): void
    {
        // Dynamic relations on Lunar ProductVariant
        ProductVariant::resolveRelationUsing('flashSaleItems', function (ProductVariant $variant) {
            return $variant->hasMany(FlashSaleItem::class, 'product_variant_id');
        });

        ProductVariant::resolveRelationUsing('dailyDeals', function (ProductVariant $variant) {
            return $variant->hasMany(DailyDeal::class, 'product_variant_id');
        });

        // Event listener for OrderDelivered -> award loyalty points
        Event::listen(OrderDelivered::class, AwardLoyaltyPointsOnOrderDelivered::class);
    }
}
