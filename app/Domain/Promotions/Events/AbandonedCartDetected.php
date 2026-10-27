<?php

declare(strict_types=1);

namespace App\Domain\Promotions\Events;

use App\Models\AbandonedCart;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AbandonedCartDetected
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public AbandonedCart $abandonedCart
    ) {}
}
