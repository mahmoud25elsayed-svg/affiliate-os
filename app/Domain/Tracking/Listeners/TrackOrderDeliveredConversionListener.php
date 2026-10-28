<?php

declare(strict_types=1);

namespace App\Domain\Tracking\Listeners;

use App\Domain\Orders\Events\OrderDelivered;
use App\Domain\Tracking\Services\AdTrackingManager;

class TrackOrderDeliveredConversionListener
{
    public function __construct(
        protected AdTrackingManager $trackingManager
    ) {}

    public function handle(OrderDelivered $event): void
    {
        $this->trackingManager->trackOrderDelivered($event->order);
    }
}
