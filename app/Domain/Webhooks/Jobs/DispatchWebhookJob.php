<?php

declare(strict_types=1);

namespace App\Domain\Webhooks\Jobs;

use App\Models\WebhookDelivery;
use App\Models\WebhookSubscription;
use App\Support\Security\Exceptions\SsrfBlockedException;
use App\Support\Security\SsrfProtection;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class DispatchWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /**
     * @var list<int>
     */
    public array $backoff = [10, 60, 300];

    public function __construct(
        public int $deliveryId
    ) {}

    public function handle(): void
    {
        /** @var WebhookDelivery|null $delivery */
        $delivery = WebhookDelivery::with('subscription')->find($this->deliveryId);
        if (! $delivery) {
            return;
        }

        /** @var WebhookSubscription|null $subscription */
        $subscription = $delivery->subscription;
        if (! $subscription || ! $subscription->is_active) {
            $delivery->update(['status' => WebhookDelivery::STATUS_FAILED]);

            return;
        }

        $payloadJson = (string) json_encode($delivery->payload);
        $signature = 'sha256='.hash_hmac('sha256', $payloadJson, $subscription->secret);

        $delivery->attempt = $this->attempts();

        try {
            SsrfProtection::assertSafeUrl($subscription->url);
        } catch (SsrfBlockedException $e) {
            $delivery->status = WebhookDelivery::STATUS_FAILED;
            $delivery->response_status = 400;
            $delivery->response_body = 'Blocked by SSRF protection: '.$e->getMessage();
            $delivery->save();

            $subscription->recordFailure();
            Log::warning("Webhook delivery [{$delivery->id}] blocked by SSRF protection for url [{$subscription->url}]");

            return;
        }

        try {
            $response = Http::timeout(10)
                ->withHeaders([
                    'X-AffiliateOS-Event' => $delivery->event,
                    'X-AffiliateOS-Signature' => $signature,
                    'X-AffiliateOS-Delivery' => (string) $delivery->id,
                    'Content-Type' => 'application/json',
                    'User-Agent' => 'AffiliateOS-Webhook-Dispatcher/1.0',
                ])
                ->withBody($payloadJson, 'application/json')
                ->post($subscription->url);

            $status = $response->status();
            $body = substr($response->body(), 0, 1000);

            $delivery->response_status = $status;
            $delivery->response_body = $body;

            if ($response->successful()) {
                $delivery->status = WebhookDelivery::STATUS_DELIVERED;
                $delivery->delivered_at = now();
                $delivery->save();

                $subscription->recordSuccess();

                return;
            }

            // HTTP Error (4xx, 5xx)
            $delivery->status = WebhookDelivery::STATUS_FAILED;
            $delivery->save();
            $subscription->recordFailure();

            throw new \RuntimeException("Webhook failed with status code [{$status}]: {$body}");
        } catch (Throwable $e) {
            Log::warning("Webhook delivery attempt [{$this->attempts()}] failed for delivery [{$delivery->id}]: ".$e->getMessage());

            if ($delivery->status !== WebhookDelivery::STATUS_DELIVERED) {
                $delivery->status = WebhookDelivery::STATUS_FAILED;
                $delivery->response_body = substr($e->getMessage(), 0, 1000);
                $delivery->save();
            }

            throw $e;
        }
    }
}
