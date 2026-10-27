<?php

declare(strict_types=1);

namespace App\Domain\Promotions\Events;

use App\Models\CustomerLoyaltyAccount;
use App\Models\CustomerLoyaltyTransaction;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Lunar\Models\Order;

class LoyaltyPointsEarned
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public CustomerLoyaltyAccount $account,
        public CustomerLoyaltyTransaction $transaction,
        public ?Order $order = null
    ) {}
}
