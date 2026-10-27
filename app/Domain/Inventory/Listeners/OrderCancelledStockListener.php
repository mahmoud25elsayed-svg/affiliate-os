<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Listeners;

use App\Domain\Inventory\Actions\ReleaseOrderStockAction;
use App\Domain\Orders\Events\OrderCancelled;

class OrderCancelledStockListener
{
    public function __construct(
        protected readonly ReleaseOrderStockAction $releaseOrderStockAction
    ) {}

    public function handle(OrderCancelled $event): void
    {
        $this->releaseOrderStockAction->execute(
            order: $event->order,
            actorType: $event->actor ? $event->actor->type : 'system',
            actorId: $event->actor ? $event->actor->id : null
        );
    }
}
