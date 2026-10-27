<?php

declare(strict_types=1);

namespace App\Jobs\Subscriptions;

use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SuspendDelinquentSubscriptions implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): int
    {
        $now = now();
        $count = 0;

        $delinquentSubscriptions = Subscription::where('status', Subscription::STATUS_PAST_DUE)
            ->whereNotNull('grace_period_ends_at')
            ->where('grace_period_ends_at', '<', $now)
            ->get();

        foreach ($delinquentSubscriptions as $subscription) {
            $subscription->update([
                'status' => Subscription::STATUS_SUSPENDED,
            ]);

            // Mark tenant suspended (storefront closed, admin redirected to billing, data strictly preserved)
            $tenant = $subscription->tenant;
            if (! $tenant->isSuspended()) {
                $tenant->update(['status' => 'suspended']);
            }

            $subscription->events()->create([
                'tenant_id' => $subscription->tenant_id,
                'event' => 'suspended',
                'metadata' => [
                    'reason' => 'grace_period_expired_without_payment',
                ],
                'created_at' => $now,
            ]);

            Log::error("Tenant {$subscription->tenant_id} suspended due to expired grace period.");
            $count++;
        }

        return $count;
    }
}
