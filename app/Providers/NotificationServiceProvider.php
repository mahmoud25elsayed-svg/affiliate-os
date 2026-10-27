<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Notifications\Listeners\DispatchAbandonedCartNotification;
use App\Domain\Notifications\Listeners\DispatchOrderNotification;
use App\Domain\Notifications\Services\NotificationManager;
use App\Domain\Notifications\Services\TemplateEngine;
use App\Domain\Orders\Events\OrderCancelled;
use App\Domain\Orders\Events\OrderConfirmed;
use App\Domain\Orders\Events\OrderDelivered;
use App\Domain\Orders\Events\OrderDeliveryFailed;
use App\Domain\Orders\Events\OrderShipped;
use App\Domain\Promotions\Events\AbandonedCartDetected;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class NotificationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(TemplateEngine::class);
        $this->app->singleton(NotificationManager::class);
    }

    public function boot(): void
    {
        // Order lifecycle notification events
        Event::listen(OrderConfirmed::class, DispatchOrderNotification::class);
        Event::listen(OrderShipped::class, DispatchOrderNotification::class);
        Event::listen(OrderDelivered::class, DispatchOrderNotification::class);
        Event::listen(OrderCancelled::class, DispatchOrderNotification::class);
        Event::listen(OrderDeliveryFailed::class, DispatchOrderNotification::class);

        // Abandoned cart reminder events
        Event::listen(AbandonedCartDetected::class, DispatchAbandonedCartNotification::class);
    }
}
