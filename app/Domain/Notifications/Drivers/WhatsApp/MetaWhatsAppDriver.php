<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Drivers\WhatsApp;

use App\Domain\Notifications\Contracts\NotificationDriverInterface;
use App\Domain\Notifications\DTOs\NotificationMessage;
use App\Domain\Notifications\DTOs\NotificationResult;
use Illuminate\Support\Facades\Http;
use Throwable;

class MetaWhatsAppDriver implements NotificationDriverInterface
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function send(NotificationMessage $message, array $config = []): NotificationResult
    {
        $accessToken = (string) ($config['access_token'] ?? config('services.whatsapp.access_token', ''));
        $phoneNumberId = (string) ($config['phone_number_id'] ?? config('services.whatsapp.phone_number_id', ''));

        if (empty($accessToken) || empty($phoneNumberId)) {
            return NotificationResult::failure('Meta WhatsApp Cloud API credentials not configured.');
        }

        $recipient = preg_replace('/^\+|^00/', '', $message->recipient);
        $url = "https://graph.facebook.com/v19.0/{$phoneNumberId}/messages";

        try {
            $payload = [
                'messaging_product' => 'whatsapp',
                'recipient_type' => 'individual',
                'to' => $recipient,
                'type' => 'text',
                'text' => [
                    'preview_url' => true,
                    'body' => $message->content,
                ],
            ];

            $response = Http::timeout(5)
                ->withToken($accessToken)
                ->post($url, $payload);

            if (! $response->successful()) {
                return NotificationResult::failure(
                    "Meta WhatsApp API error: {$response->status()}",
                    ['body' => $response->body()]
                );
            }

            /** @var array<string, mixed>|null $data */
            $data = $response->json();
            $messages = $data['messages'] ?? [];
            $externalId = isset($messages[0]['id']) ? (string) $messages[0]['id'] : uniqid('waba_');

            return NotificationResult::success(
                externalId: $externalId,
                costMinor: 40, // 40 piastres (0.40 EGP per utility message)
                rawResponse: $data ?? []
            );
        } catch (Throwable $e) {
            return NotificationResult::failure(
                'Meta WhatsApp network exception: '.$e->getMessage()
            );
        }
    }

    public function getProviderName(): string
    {
        return 'meta_whatsapp';
    }
}
