<?php

declare(strict_types=1);

namespace App\Domain\Orders\Events;

use App\Domain\Orders\DTOs\OrderActor;
use App\Models\OrderEvent;
use App\Support\Enums\ReasonCode;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Lunar\Models\Order;

abstract class OrderStateEvent implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    /**
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public readonly Order $order,
        public readonly OrderEvent $orderEvent,
        public readonly ?ReasonCode $reasonCode = null,
        public readonly ?OrderActor $actor = null,
        public readonly array $meta = []
    ) {}
}
