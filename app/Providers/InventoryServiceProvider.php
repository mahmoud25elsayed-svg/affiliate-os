<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Inventory\Listeners\OrderCancelledStockListener;
use App\Domain\Inventory\Listeners\OrderConfirmedStockListener;
use App\Domain\Inventory\Listeners\OrderReturnedStockListener;
use App\Domain\Inventory\Listeners\OrderShippedStockListener;
use App\Domain\Orders\Events\OrderCancelled;
use App\Domain\Orders\Events\OrderConfirmed;
use App\Domain\Orders\Events\OrderReturned;
use App\Domain\Orders\Events\OrderShipped;
use App\Models\InventoryBalance;
use App\Models\InventoryMovement;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Lunar\Models\ProductVariant;

class InventoryServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        // Dynamic relations on Lunar ProductVariant
        ProductVariant::resolveRelationUsing('inventoryBalances', function (ProductVariant $variant) {
            return $variant->hasMany(InventoryBalance::class, 'product_variant_id');
        });

        ProductVariant::resolveRelationUsing('inventoryMovements', function (ProductVariant $variant) {
            return $variant->hasMany(InventoryMovement::class, 'product_variant_id');
        });

        // Event Listeners for Order Lifecycle Stock automation
        Event::listen(OrderConfirmed::class, OrderConfirmedStockListener::class);
        Event::listen(OrderCancelled::class, OrderCancelledStockListener::class);
        Event::listen(OrderShipped::class, OrderShippedStockListener::class);
        Event::listen(OrderReturned::class, OrderReturnedStockListener::class);
    }
}
