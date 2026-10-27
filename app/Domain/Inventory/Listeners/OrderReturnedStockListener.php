<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Listeners;

use App\Domain\Inventory\Actions\ReturnOrderStockAction;
use App\Domain\Orders\Events\OrderReturned;
use App\Support\Enums\ReasonCode;

class OrderReturnedStockListener
{
    public function __construct(
        protected readonly ReturnOrderStockAction $returnOrderStockAction
    ) {}

    public function handle(OrderReturned $event): void
    {
        $isSound = $event->reasonCode !== ReasonCode::DEFECTIVE_PRODUCT;

        $this->returnOrderStockAction->execute(
            order: $event->order,
            isSound: $isSound,
            actorType: $event->actor ? $event->actor->type : 'system',
            actorId: $event->actor ? $event->actor->id : null,
            notes: $event->reasonCode?->label('ar')
        );
    }
}
