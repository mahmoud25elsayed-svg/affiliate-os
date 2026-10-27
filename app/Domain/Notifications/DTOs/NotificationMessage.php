<?php

declare(strict_types=1);

namespace App\Domain\Notifications\DTOs;

use App\Domain\Notifications\Enums\NotificationChannel;
use Illuminate\Database\Eloquent\Model;

class NotificationMessage
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public readonly NotificationChannel $channel,
        public readonly string $recipient,
        public readonly string $content,
        public readonly ?string $subject = null,
        public readonly string $eventType = 'custom',
        public readonly ?string $senderId = null,
        public readonly array $metadata = [],
        public readonly ?Model $notifiable = null
    ) {}
}
