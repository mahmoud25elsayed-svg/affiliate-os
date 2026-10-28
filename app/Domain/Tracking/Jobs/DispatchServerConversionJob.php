<?php

declare(strict_types=1);

namespace App\Domain\Tracking\Jobs;

use App\Domain\Tracking\Services\MetaConversionsApiService;
use App\Domain\Tracking\Services\TikTokEventsApiService;
use App\Models\AdConversionEvent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class DispatchServerConversionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /**
     * @var list<int>
     */
    public array $backoff = [10, 60, 300];

    /**
     * @param  array<string, mixed>  $userData
     * @param  array<string, mixed>  $customData
     */
    public function __construct(
        public string $tenantId,
        public string $platform,
        public string $pixelId,
        public string $accessToken,
        public string $eventName,
        public string $eventId,
        public array $userData,
        public array $customData = [],
        public ?string $eventSourceUrl = null,
        public ?string $testEventCode = null,
        public ?int $orderId = null,
        public ?int $affiliateId = null
    ) {}

    public function handle(
        MetaConversionsApiService $metaService,
        TikTokEventsApiService $tiktokService
    ): void {
        if ($this->platform === AdConversionEvent::PLATFORM_META) {
            $metaService->sendEvent(
                tenantId: $this->tenantId,
                pixelId: $this->pixelId,
                accessToken: $this->accessToken,
                eventName: $this->eventName,
                eventId: $this->eventId,
                userData: $this->userData,
                customData: $this->customData,
                eventSourceUrl: $this->eventSourceUrl,
                testEventCode: $this->testEventCode,
                orderId: $this->orderId,
                affiliateId: $this->affiliateId,
            );
        } elseif ($this->platform === AdConversionEvent::PLATFORM_TIKTOK) {
            $tiktokService->sendEvent(
                tenantId: $this->tenantId,
                pixelId: $this->pixelId,
                accessToken: $this->accessToken,
                eventName: $this->eventName,
                eventId: $this->eventId,
                userData: $this->userData,
                customData: $this->customData,
                eventSourceUrl: $this->eventSourceUrl,
                testEventCode: $this->testEventCode,
                orderId: $this->orderId,
                affiliateId: $this->affiliateId,
            );
        }
    }
}
