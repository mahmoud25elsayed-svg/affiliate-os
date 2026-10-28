<?php

declare(strict_types=1);

namespace App\Domain\Tracking\Services;

use App\Models\AdConversionEvent;
use Illuminate\Support\Facades\Http;
use Throwable;

class MetaConversionsApiService
{
    public const API_VERSION = 'v21.0';

    /**
     * Send event to Meta Conversions API (Graph API).
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
        $metaUserData = [];

        if (! empty($userData['email'])) {
            $metaUserData['em'] = [ConversionDataHasher::hashEmail((string) $userData['email'])];
        }

        if (! empty($userData['phone'])) {
            $metaUserData['ph'] = [ConversionDataHasher::hashPhone((string) $userData['phone'])];
        }

        if (! empty($userData['first_name'])) {
            $metaUserData['fn'] = [ConversionDataHasher::hash((string) $userData['first_name'])];
        }

        if (! empty($userData['last_name'])) {
            $metaUserData['ln'] = [ConversionDataHasher::hash((string) $userData['last_name'])];
        }

        if (! empty($userData['city'])) {
            $metaUserData['ct'] = [ConversionDataHasher::hashCity((string) $userData['city'])];
        }

        $country = $userData['country'] ?? 'eg';
        $metaUserData['country'] = [ConversionDataHasher::hash((string) $country)];

        if (! empty($userData['client_ip_address'])) {
            $metaUserData['client_ip_address'] = (string) $userData['client_ip_address'];
        }

        if (! empty($userData['client_user_agent'])) {
            $metaUserData['client_user_agent'] = (string) $userData['client_user_agent'];
        }

        if (! empty($userData['fbp'])) {
            $metaUserData['fbp'] = (string) $userData['fbp'];
        }

        if (! empty($userData['fbc'])) {
            $metaUserData['fbc'] = (string) $userData['fbc'];
        }

        $eventPayload = [
            'event_name' => $eventName,
            'event_time' => now()->timestamp,
            'event_id' => $eventId,
            'event_source_url' => $eventSourceUrl ?? url('/'),
            'action_source' => 'website',
            'user_data' => $metaUserData,
            'custom_data' => $customData,
        ];

        $requestBody = [
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
            'platform' => AdConversionEvent::PLATFORM_META,
            'pixel_id' => $pixelId,
            'event_name' => $eventName,
            'event_id' => $eventId,
            'payload' => $requestBody,
            'status' => AdConversionEvent::STATUS_PENDING,
        ]);

        try {
            $response = Http::timeout(10)
                ->asJson()
                ->post('https://graph.facebook.com/'.self::API_VERSION."/{$pixelId}/events?access_token={$accessToken}", $requestBody);

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
