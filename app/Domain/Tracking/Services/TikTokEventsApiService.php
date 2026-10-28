<?php

declare(strict_types=1);

namespace App\Domain\Tracking\Services;

use App\Models\AdConversionEvent;
use Illuminate\Support\Facades\Http;
use Throwable;

class TikTokEventsApiService
{
    public const API_URL = 'https://business-api.tiktok.com/open_api/v1.3/event/track/';

    /**
     * Send event to TikTok Events API.
     *
     * @param  array<string, mixed>  $userData
     * @param  array<string, mixed>  $customData
     */
    public function sendEvent(
        string $tenantId,
        string $pixelId,
        string $accessToken,
        string $eventName,
        string $eventId,
        array $userData,
        array $customData = [],
        ?string $eventSourceUrl = null,
        ?string $testEventCode = null,
        ?int $orderId = null,
        ?int $affiliateId = null
    ): AdConversionEvent {
        $tiktokUser = [];

        if (! empty($userData['email'])) {
            $tiktokUser['email'] = ConversionDataHasher::hashEmail((string) $userData['email']);
        }

        if (! empty($userData['phone'])) {
            $tiktokUser['phone'] = ConversionDataHasher::hashPhone((string) $userData['phone']);
        }

        if (! empty($userData['client_ip_address'])) {
            $tiktokUser['ip'] = (string) $userData['client_ip_address'];
        }

        if (! empty($userData['client_user_agent'])) {
            $tiktokUser['user_agent'] = (string) $userData['client_user_agent'];
        }

        if (! empty($userData['ttclid'])) {
            $tiktokUser['ttclid'] = (string) $userData['ttclid'];
        }

        $eventPayload = [
            'event' => $eventName,
            'event_time' => now()->timestamp,
            'event_id' => $eventId,
            'user' => $tiktokUser,
            'properties' => $customData,
        ];

        if (! empty($eventSourceUrl)) {
            $eventPayload['page'] = ['url' => $eventSourceUrl];
        }

        $requestBody = [
            'event_source' => 'web',
            'event_source_id' => $pixelId,
            'data' => [$eventPayload],
        ];

        if (! empty($testEventCode)) {
            $requestBody['test_event_code'] = $testEventCode;
        }

        /** @var AdConversionEvent $logRecord */
        $logRecord = AdConversionEvent::create([
            'tenant_id' => $tenantId,
            'order_id' => $orderId,
            'affiliate_id' => $affiliateId,
            'platform' => AdConversionEvent::PLATFORM_TIKTOK,
            'pixel_id' => $pixelId,
            'event_name' => $eventName,
            'event_id' => $eventId,
            'payload' => $requestBody,
            'status' => AdConversionEvent::STATUS_PENDING,
        ]);

        try {
            $response = Http::timeout(10)
                ->withHeaders([
                    'Access-Token' => $accessToken,
                    'Content-Type' => 'application/json',
                ])
                ->post(self::API_URL, $requestBody);

            $status = $response->status();
            $body = substr($response->body(), 0, 2000);

            $logRecord->update([
                'response_code' => $status,
                'response_body' => $body,
                'status' => $response->successful() ? AdConversionEvent::STATUS_SENT : AdConversionEvent::STATUS_FAILED,
                'sent_at' => now(),
            ]);
        } catch (Throwable $e) {
            $logRecord->update([
                'status' => AdConversionEvent::STATUS_FAILED,
                'error_message' => $e->getMessage(),
            ]);
        }

        return $logRecord;
    }
}
