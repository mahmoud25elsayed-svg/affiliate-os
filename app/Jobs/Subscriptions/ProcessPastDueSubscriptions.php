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

class ProcessPastDueSubscriptions implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): int
    {
        $now = now();
        $count = 0;

        // Subscriptions in 'trial' or 'active' whose period/trial has elapsed
        $dueSubscriptions = Subscription::whereIn('status', [Subscription::STATUS_TRIAL, Subscription::STATUS_ACTIVE])
            ->where(function ($query) use ($now) {
                $query->where(function ($q) use ($now) {
                    $q->where('status', Subscription::STATUS_TRIAL)
                        ->where('trial_ends_at', '<', $now);
                })->orWhere(function ($q) use ($now) {
                    $q->where('status', Subscription::STATUS_ACTIVE)
                        ->where('current_period_ends_at', '<', $now);
                });
            })
            ->get();

        foreach ($dueSubscriptions as $subscription) {
            $previousStatus = $subscription->status;
            $gracePeriodEndsAt = $now->copy()->addDays(3);

            $subscription->update([
                'status' => Subscription::STATUS_PAST_DUE,
                'grace_period_ends_at' => $gracePeriodEndsAt,
            ]);

            $subscription->events()->create([
                'tenant_id' => $subscription->tenant_id,
                'event' => 'past_due',
                'metadata' => [
                    'previous_status' => $previousStatus,
                    'grace_period_ends_at' => $gracePeriodEndsAt->toIso8601String(),
                ],
                'created_at' => $now,
            ]);

            Log::warning("Subscription transitioned to past_due for tenant: {$subscription->tenant_id}");
            $count++;
        }

        return $count;
    }
}
