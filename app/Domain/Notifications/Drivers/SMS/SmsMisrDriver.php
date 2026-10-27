<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Drivers\SMS;

use App\Domain\Notifications\Contracts\NotificationDriverInterface;
use App\Domain\Notifications\DTOs\NotificationMessage;
use App\Domain\Notifications\DTOs\NotificationResult;
use Illuminate\Support\Facades\Http;
use Throwable;

class SmsMisrDriver implements NotificationDriverInterface
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function send(NotificationMessage $message, array $config = []): NotificationResult
    {
        $username = (string) ($config['username'] ?? config('services.smsmisr.username', ''));
        $password = (string) ($config['password'] ?? config('services.smsmisr.password', ''));
        $senderId = (string) ($config['sender_id'] ?? $message->senderId ?? config('services.smsmisr.sender_id', 'AffiliateOS'));
        $endpoint = (string) ($config['endpoint'] ?? config('services.smsmisr.endpoint', 'https://smsmisr.com/api/v2/'));

        if (empty($username) || empty($password)) {
            return NotificationResult::failure('SMS Misr credentials not configured.');
        }

        // Clean phone number (strip + and 00 for SMS Misr standard EG formatting)
        $mobile = preg_replace('/^\+|^00/', '', $message->recipient);

        try {
            $response = Http::timeout(5)->asForm()->post($endpoint, [
                'username' => $username,
                'password' => $password,
                'language' => 2, // Arabic
                'sender' => $senderId,
                'mobile' => $mobile,
                'message' => $message->content,
            ]);

            if (! $response->successful()) {
                return NotificationResult::failure(
                    "SMS Misr HTTP error: {$response->status()}",
                    ['body' => $response->body()]
                );
            }

            /** @var array<string, mixed>|null $data */
            $data = $response->json();
            $code = (string) ($data['code'] ?? '');

            // In SMS Misr, 1901 indicates success, 1902 invalid credentials, etc.
            if ($code === '1901' || ($data['status'] ?? '') === 'success') {
                return NotificationResult::success(
                    externalId: (string) ($data['id'] ?? uniqid('sms_')),
                    costMinor: 30, // 30 piastres (0.30 EGP)
                    rawResponse: $data ?? []
                );
            }

            return NotificationResult::failure(
                'SMS Misr returned error code: '.($code ?: 'unknown'),
                $data ?? ['body' => $response->body()]
            );
        } catch (Throwable $e) {
            return NotificationResult::failure(
                'SMS Misr network exception: '.$e->getMessage()
            );
        }
    }

    public function getProviderName(): string
    {
        return 'sms_misr';
    }
}
