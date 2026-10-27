<?php

declare(strict_types=1);

namespace App\Domain\Promotions\Events;

use App\Models\FlashSaleItem;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class FlashSaleStockDepleted
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public FlashSaleItem $flashSaleItem
    ) {}
}
