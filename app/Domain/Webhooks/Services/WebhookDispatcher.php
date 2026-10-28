<?php

declare(strict_types=1);

namespace App\Domain\Webhooks\Services;

use App\Domain\Webhooks\Jobs\DispatchWebhookJob;
use App\Models\WebhookDelivery;
use App\Models\WebhookSubscription;

class WebhookDispatcher
{
    /**
     * Dispatch an event to all matching active webhook subscriptions for the current tenant.
     *
     * @param  array<string, mixed>  $payload
     */
    public function dispatch(string $event, array $payload, ?string $tenantId = null): void
    {
        $tenantId = $tenantId ?? (function_exists('tenant') && tenant('id') ? (string) tenant('id') : null);
        if (! $tenantId) {
            return;
        }

        $subscriptions = WebhookSubscription::query()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->get();

        foreach ($subscriptions as $subscription) {
            if ($subscription->subscribesTo($event)) {
                /** @var WebhookDelivery $delivery */
                $delivery = WebhookDelivery::create([
                    'tenant_id' => $tenantId,
                    'webhook_subscription_id' => $subscription->id,
                    'event' => $event,
                    'payload' => $payload,
                    'status' => WebhookDelivery::STATUS_PENDING,
                    'attempt' => 1,
                ]);

                DispatchWebhookJob::dispatch($delivery->id);
            }
        }
    }
}
