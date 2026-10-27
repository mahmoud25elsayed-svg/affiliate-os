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

class CheckExpiringSubscriptions implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): int
    {
        $threeDaysFromNow = now()->addDays(3);
        $count = 0;

        // 1. Trials expiring in next 3 days
        $expiringTrials = Subscription::where('status', Subscription::STATUS_TRIAL)
            ->whereNotNull('trial_ends_at')
            ->whereBetween('trial_ends_at', [now(), $threeDaysFromNow])
            ->get();

        foreach ($expiringTrials as $subscription) {
            $metadata = $subscription->metadata ?? [];
            if (! empty($metadata['trial_reminder_sent_at'])) {
                continue;
            }

            $metadata['trial_reminder_sent_at'] = now()->toIso8601String();
            $subscription->update(['metadata' => $metadata]);

            $subscription->events()->create([
                'tenant_id' => $subscription->tenant_id,
                'event' => 'trial_expiring_soon',
                'metadata' => [
                    'trial_ends_at' => $subscription->trial_ends_at?->toIso8601String(),
                    'days_left' => $subscription->daysLeftInTrial(),
                ],
                'created_at' => now(),
            ]);

            Log::info("Trial expiring soon for tenant: {$subscription->tenant_id}");
            $count++;
        }

        // 2. Active subscriptions expiring in next 3 days
        $expiringActive = Subscription::where('status', Subscription::STATUS_ACTIVE)
            ->whereNotNull('current_period_ends_at')
            ->whereBetween('current_period_ends_at', [now(), $threeDaysFromNow])
            ->get();

        foreach ($expiringActive as $subscription) {
            $metadata = $subscription->metadata ?? [];
            $periodKey = 'renewal_reminder_'.$subscription->current_period_ends_at?->format('Y-m-d');

            if (! empty($metadata[$periodKey])) {
                continue;
            }

            $metadata[$periodKey] = now()->toIso8601String();
            $subscription->update(['metadata' => $metadata]);

            $subscription->events()->create([
                'tenant_id' => $subscription->tenant_id,
                'event' => 'renewal_upcoming',
                'metadata' => [
                    'current_period_ends_at' => $subscription->current_period_ends_at?->toIso8601String(),
                    'days_left' => $subscription->daysLeftInPeriod(),
                ],
                'created_at' => now(),
            ]);

            Log::info("Renewal upcoming for tenant: {$subscription->tenant_id}");
            $count++;
        }

        return $count;
    }
}
