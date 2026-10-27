<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Jobs;

use App\Domain\Notifications\Enums\NotificationChannel;
use App\Domain\Notifications\Services\NotificationManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class DispatchNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public readonly string $channel,
        public readonly string $recipient,
        public readonly string $eventType,
        public readonly array $data = [],
        public readonly string $locale = 'ar',
        public readonly ?string $notifiableType = null,
        public readonly ?int $notifiableId = null,
        public readonly array $metadata = []
    ) {}

    public function handle(NotificationManager $manager): void
    {
        $notifiable = null;
        if ($this->notifiableType && $this->notifiableId && class_exists($this->notifiableType)) {
            /** @var class-string<Model> $modelClass */
            $modelClass = $this->notifiableType;
            $notifiable = $modelClass::find($this->notifiableId);
        }

        $manager->send(
            channel: NotificationChannel::from($this->channel),
            recipient: $this->recipient,
            eventType: $this->eventType,
            data: $this->data,
            locale: $this->locale,
            notifiable: $notifiable,
            metadata: $this->metadata
        );
    }
}
