<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Services;

use App\Domain\Notifications\Contracts\NotificationDriverInterface;
use App\Domain\Notifications\Drivers\Email\SystemMailDriver;
use App\Domain\Notifications\Drivers\InApp\DatabaseInAppDriver;
use App\Domain\Notifications\Drivers\SMS\SmsMisrDriver;
use App\Domain\Notifications\Drivers\Testing\FakeNotificationDriver;
use App\Domain\Notifications\Drivers\WhatsApp\MetaWhatsAppDriver;
use App\Domain\Notifications\DTOs\NotificationMessage;
use App\Domain\Notifications\DTOs\NotificationResult;
use App\Domain\Notifications\Enums\NotificationChannel as ChannelEnum;
use App\Domain\Notifications\Jobs\DispatchNotificationJob;
use App\Models\NotificationChannel;
use App\Models\NotificationLog;
use App\Models\NotificationTemplate;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Throwable;

class NotificationManager
{
    /**
     * @var array<string, NotificationDriverInterface>
     */
    protected array $customDrivers = [];

    protected ?FakeNotificationDriver $fakeDriver = null;

    public function __construct(
        protected TemplateEngine $templateEngine
    ) {}

    public function fake(): FakeNotificationDriver
    {
        $this->fakeDriver = new FakeNotificationDriver;

        return $this->fakeDriver;
    }

    public function isFaked(): bool
    {
        return $this->fakeDriver !== null;
    }

    public function getFakeDriver(): ?FakeNotificationDriver
    {
        return $this->fakeDriver;
    }

    public function registerDriver(string $provider, NotificationDriverInterface $driver): void
    {
        $this->customDrivers[$provider] = $driver;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $metadata
     */
    public function send(
        ChannelEnum|string $channel,
        string $recipient,
        string $eventType,
        array $data = [],
        string $locale = 'ar',
        ?Model $notifiable = null,
        array $metadata = []
    ): NotificationLog {
        $channelEnum = is_string($channel) ? ChannelEnum::from($channel) : $channel;
        $tenant = function_exists('tenancy') && tenancy()->initialized ? tenancy()->tenant : null;
        $tenantId = $tenant instanceof Tenant ? (string) $tenant->id : '';

        // 1. Resolve Active Channel Configuration for Tenant
        $channelConfig = $this->resolveChannelConfig($tenantId, $channelEnum);

        // 2. Check Daily Cost Limit
        if ($channelConfig instanceof NotificationChannel && ! $channelConfig->canSpend(30)) {
            return NotificationLog::create([
                'tenant_id' => $tenantId,
                'channel' => $channelEnum->value,
                'provider' => $channelConfig->provider,
                'recipient' => $recipient,
                'event_type' => $eventType,
                'content' => 'Blocked by daily cost limit',
                'status' => 'failed',
                'error_message' => 'Daily spend limit exceeded for channel: '.$channelEnum->value,
                'cost_minor' => 0,
                'notifiable_type' => $notifiable ? get_class($notifiable) : null,
                'notifiable_id' => $notifiable ? $notifiable->getKey() : null,
            ]);
        }

        // 3. Resolve and Render Template
        $rendered = $this->renderMessageContent($tenantId, $channelEnum, $eventType, $data, $locale);
        $subject = $rendered['subject'];
        $content = $rendered['content'];

        $message = new NotificationMessage(
            channel: $channelEnum,
            recipient: $recipient,
            content: $content,
            subject: $subject,
            eventType: $eventType,
            senderId: $channelConfig instanceof NotificationChannel ? $channelConfig->sender_id : null,
            metadata: $metadata,
            notifiable: $notifiable
        );

        // 4. Resolve Driver and Send
        $providerName = $channelConfig instanceof NotificationChannel ? $channelConfig->provider : 'default';
        $driver = $this->resolveDriver($providerName, $channelEnum);

        $driverConfig = $channelConfig instanceof NotificationChannel
            ? (array) ($channelConfig->credentials ?? [])
            : [];

        try {
            $result = $driver->send($message, $driverConfig);
        } catch (Throwable $e) {
            $result = NotificationResult::failure('Driver dispatch exception: '.$e->getMessage());
        }

        // 5. Persist Log (Guaranteed Non-Blocking). One-time codes are never stored: anyone who can
        // read the log could otherwise sign in as the recipient.
        $log = NotificationLog::create([
            'tenant_id' => $tenantId,
            'channel' => $channelEnum->value,
            'provider' => $driver->getProviderName(),
            'recipient' => $recipient,
            'event_type' => $eventType,
            'subject' => $subject,
            'content' => str_contains($eventType, 'otp') ? '[redacted one-time code message]' : $content,
            'status' => $result->success ? 'sent' : 'failed',
            'error_message' => $result->errorMessage,
            'external_id' => $result->externalId,
            'cost_minor' => $result->costMinor,
            'sent_at' => $result->success ? Carbon::now() : null,
            'notifiable_type' => $notifiable ? get_class($notifiable) : null,
            'notifiable_id' => $notifiable ? $notifiable->getKey() : null,
        ]);

        // 6. Record channel spend if successful
        if ($result->success && $result->costMinor > 0 && $channelConfig instanceof NotificationChannel) {
            $channelConfig->recordSpend($result->costMinor);
        }

        return $log;
    }

    /**
     * Queue the notification asynchronously to prevent blocking request cycles.
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $metadata
     */
    public function sendAsync(
        ChannelEnum|string $channel,
        string $recipient,
        string $eventType,
        array $data = [],
        string $locale = 'ar',
        ?Model $notifiable = null,
        array $metadata = []
    ): void {
        $channelValue = is_string($channel) ? $channel : $channel->value;
        $notifiableType = $notifiable ? get_class($notifiable) : null;
        $notifiableId = $notifiable ? (int) $notifiable->getKey() : null;

        DispatchNotificationJob::dispatch(
            $channelValue,
            $recipient,
            $eventType,
            $data,
            $locale,
            $notifiableType,
            $notifiableId,
            $metadata
        );
    }

    protected function resolveChannelConfig(string $tenantId, ChannelEnum $channel): ?NotificationChannel
    {
        if (empty($tenantId)) {
            return null;
        }

        /** @var NotificationChannel|null $configured */
        $configured = NotificationChannel::query()
            ->where('tenant_id', $tenantId)
            ->where('channel', $channel->value)
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->first();

        return $configured;
    }

    protected function resolveDriver(string $provider, ChannelEnum $channel): NotificationDriverInterface
    {
        if ($this->fakeDriver !== null) {
            return $this->fakeDriver;
        }

        if (isset($this->customDrivers[$provider])) {
            return $this->customDrivers[$provider];
        }

        return match ($provider) {
            'sms_misr' => new SmsMisrDriver,
            'meta_whatsapp' => new MetaWhatsAppDriver,
            'system_mail' => new SystemMailDriver,
            'in_app' => new DatabaseInAppDriver,
            'fake' => new FakeNotificationDriver,
            default => match ($channel) {
                ChannelEnum::SMS => new SmsMisrDriver,
                ChannelEnum::WHATSAPP => new MetaWhatsAppDriver,
                ChannelEnum::EMAIL => new SystemMailDriver,
                ChannelEnum::IN_APP => new DatabaseInAppDriver,
                default => new FakeNotificationDriver,
            },
        };
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{subject: ?string, content: string}
     */
    protected function renderMessageContent(
        string $tenantId,
        ChannelEnum $channel,
        string $eventType,
        array $data,
        string $locale
    ): array {
        // Look up custom template in DB if tenant is present
        if (! empty($tenantId)) {
            /** @var NotificationTemplate|null $customTemplate */
            $customTemplate = NotificationTemplate::query()
                ->where('tenant_id', $tenantId)
                ->where('event_type', $eventType)
                ->where('channel', $channel->value)
                ->where('locale', $locale)
                ->where('is_active', true)
                ->first();

            if ($customTemplate) {
                return [
                    'subject' => $customTemplate->renderSubject($data),
                    'content' => $customTemplate->render($data),
                ];
            }
        }

        // Fallback to default template definitions
        $defaults = $this->templateEngine->getDefaultTemplates();
        if (isset($defaults[$eventType])) {
            $defaultTpl = $defaults[$eventType];

            return [
                'subject' => $this->templateEngine->interpolate($defaultTpl['subject'], $data),
                'content' => $this->templateEngine->interpolate($defaultTpl['content'], $data),
            ];
        }

        // Generic fallback
        $fallback = __('إشعار جديد: :event_type', ['event_type' => $eventType]);

        return [
            'subject' => $fallback,
            'content' => $fallback,
        ];
    }
}
