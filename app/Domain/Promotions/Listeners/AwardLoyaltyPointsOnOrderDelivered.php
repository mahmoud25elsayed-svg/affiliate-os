<?php

declare(strict_types=1);

namespace App\Domain\Promotions\Listeners;

use App\Domain\Customer\Services\CustomerLoyaltyService;
use App\Domain\Orders\Events\OrderDelivered;
use Illuminate\Support\Facades\Log;

class AwardLoyaltyPointsOnOrderDelivered
{
    public function __construct(
        protected CustomerLoyaltyService $loyaltyService
    ) {}

    public function handle(OrderDelivered $event): void
    {
        try {
            $this->loyaltyService->awardPointsForOrder($event->order);
        } catch (\Throwable $e) {
            Log::error("Failed to award loyalty points for delivered order [{$event->order->id}]: " . $e->getMessage());
        }
    }
}
