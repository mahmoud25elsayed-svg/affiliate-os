<?php

declare(strict_types=1);

namespace App\Domain\CallCenter\Jobs;

use App\Domain\Orders\Enums\OrderStatus;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Lunar\Models\Order;

class ReleaseExpiredOrderLocksJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): int
    {
        $now = Carbon::now();

        return Order::query()
            ->where('status', OrderStatus::PENDING_CONFIRMATION->value)
            ->whereNotNull('assigned_to')
            ->whereNotNull('locked_until')
            ->where('locked_until', '<=', $now)
            ->update([
                'assigned_to' => null,
                'locked_until' => null,
            ]);
    }
}
