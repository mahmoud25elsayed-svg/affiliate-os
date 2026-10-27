<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Listeners;

use App\Domain\Inventory\Actions\ShipOrderStockAction;
use App\Domain\Orders\Events\OrderShipped;

class OrderShippedStockListener
{
    public function __construct(
        protected readonly ShipOrderStockAction $shipOrderStockAction
    ) {}

    public function handle(OrderShipped $event): void
    {
        $this->shipOrderStockAction->execute(
            order: $event->order,
            actorType: $event->actor ? $event->actor->type : 'system',
            actorId: $event->actor ? $event->actor->id : null
        );
    }
}
