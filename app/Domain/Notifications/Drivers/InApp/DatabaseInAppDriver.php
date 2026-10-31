<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Drivers\InApp;

use App\Domain\Notifications\Contracts\NotificationDriverInterface;
use App\Domain\Notifications\DTOs\NotificationMessage;
use App\Domain\Notifications\DTOs\NotificationResult;
use App\Models\InAppNotification;
use Throwable;

class DatabaseInAppDriver implements NotificationDriverInterface
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function send(NotificationMessage $message, array $config = []): NotificationResult
    {
        try {
            $userType = (string) ($message->metadata['user_type'] ?? 'customer');
            $userId = (int) ($message->metadata['user_id'] ?? (is_numeric($message->recipient) ? $message->recipient : 0));
            $title = $message->subject ?: __('إشعار جديد');
            $actionUrl = isset($message->metadata['action_url']) ? (string) $message->metadata['action_url'] : null;

            $record = InAppNotification::create([
                'user_type' => $userType,
                'user_id' => $userId,
                'title' => $title,
                'body' => $message->content,
                'action_url' => $actionUrl,
            ]);

            return NotificationResult::success(
                externalId: (string) $record->id,
                costMinor: 0,
                rawResponse: ['id' => $record->id]
            );
        } catch (Throwable $e) {
            return NotificationResult::failure(
                'In-App notification saving exception: '.$e->getMessage()
            );
        }
    }

    public function getProviderName(): string
    {
        return 'in_app';
    }
}
