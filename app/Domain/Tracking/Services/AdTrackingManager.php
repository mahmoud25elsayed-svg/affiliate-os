<?php

declare(strict_types=1);

namespace App\Domain\Tracking\Services;

use App\Domain\Tracking\Jobs\DispatchServerConversionJob;
use App\Domain\Tracking\Settings\AdTrackingSettings;
use App\Models\AdConversionEvent;
use App\Models\Affiliate;
use Lunar\Models\Order;
use Lunar\Models\OrderAddress;
use Lunar\Models\OrderLine;

class AdTrackingManager
{
    public function __construct(
        protected MetaConversionsApiService $metaService,
        protected TikTokEventsApiService $tiktokService
    ) {}

    public function getSettings(): AdTrackingSettings
    {
        return AdTrackingSettings::load();
    }

    /**
     * Deduplication Event ID generator.
     */
    public function resolveOrderEventId(Order $order, string $eventName): string
    {
        $ref = $order->customer_reference ?: ($order->reference ?: (string) $order->id);

        return "order_{$eventName}_{$ref}_{$order->id}";
    }

    /**
     * Track Order Placement / Purchase server-side.
     *
     * @param  array<string, mixed>  $clientContext
     */
    public function trackOrderPurchase(
        Order $order,
        ?string $eventId = null,
        array $clientContext = [],
        bool $sync = false
    ): void {
        $settings = $this->getSettings();
        $eventId = $eventId ?? $this->resolveOrderEventId($order, AdConversionEvent::EVENT_PURCHASE);

        $userData = $this->extractUserDataFromOrder($order, $clientContext);
        $customData = $this->extractCustomDataFromOrder($order);

        $orderAffiliateId = (int) ($order->getAttribute('affiliate_id') ?? 0);
        /** @var Affiliate|null $affiliate */
        $affiliate = $orderAffiliateId > 0 ? Affiliate::find($orderAffiliateId) : null;

        // 1. Meta CAPI (Merchant)
        if ($settings->isMetaCapiActive()) {
            $this->dispatchOrRun(
                sync: $sync,
                tenantId: (string) $order->tenant_id,
                platform: AdConversionEvent::PLATFORM_META,
                pixelId: (string) $settings->meta_pixel_id,
                accessToken: (string) $settings->meta_conversions_api_token,
                eventName: AdConversionEvent::EVENT_PURCHASE,
                eventId: $eventId,
                userData: $userData,
                customData: $customData,
                testEventCode: $settings->meta_test_event_code,
                orderId: $order->id,
                affiliateId: $affiliate?->id,
            );
        }

        // 2. TikTok Events API (Merchant)
        if ($settings->isTikTokApiActive()) {
            $this->dispatchOrRun(
                sync: $sync,
                tenantId: (string) $order->tenant_id,
                platform: AdConversionEvent::PLATFORM_TIKTOK,
                pixelId: (string) $settings->tiktok_pixel_id,
                accessToken: (string) $settings->tiktok_events_api_token,
                eventName: 'CompletePayment',
                eventId: $eventId,
                userData: $userData,
                customData: $customData,
                testEventCode: $settings->tiktok_test_event_code,
                orderId: $order->id,
                affiliateId: $affiliate?->id,
            );
        }
    }

    /**
     * Track Actual Order Delivery (DELIVERED status) server-side.
     */
    public function trackOrderDelivered(
        Order $order,
        ?string $eventId = null,
        bool $sync = false
    ): void {
        $settings = $this->getSettings();
        if (! $settings->track_delivered_event) {
            return;
        }

        $eventId = $eventId ?? $this->resolveOrderEventId($order, AdConversionEvent::EVENT_DELIVERED);
        $userData = $this->extractUserDataFromOrder($order);
        $customData = $this->extractCustomDataFromOrder($order);
        $customData['order_status'] = 'delivered';

        $orderAffiliateId = (int) ($order->getAttribute('affiliate_id') ?? 0);
        /** @var Affiliate|null $affiliate */
        $affiliate = $orderAffiliateId > 0 ? Affiliate::find($orderAffiliateId) : null;

        // 1. Meta CAPI (Merchant Delivered Event)
        if ($settings->isMetaCapiActive()) {
            $this->dispatchOrRun(
                sync: $sync,
                tenantId: (string) $order->tenant_id,
                platform: AdConversionEvent::PLATFORM_META,
                pixelId: (string) $settings->meta_pixel_id,
                accessToken: (string) $settings->meta_conversions_api_token,
                eventName: AdConversionEvent::EVENT_DELIVERED,
                eventId: $eventId,
                userData: $userData,
                customData: $customData,
                testEventCode: $settings->meta_test_event_code,
                orderId: $order->id,
                affiliateId: $affiliate?->id,
            );
        }

        // 2. TikTok Events API (Merchant Delivered Event)
        if ($settings->isTikTokApiActive()) {
            $this->dispatchOrRun(
                sync: $sync,
                tenantId: (string) $order->tenant_id,
                platform: AdConversionEvent::PLATFORM_TIKTOK,
                pixelId: (string) $settings->tiktok_pixel_id,
                accessToken: (string) $settings->tiktok_events_api_token,
                eventName: AdConversionEvent::EVENT_DELIVERED,
                eventId: $eventId,
                userData: $userData,
                customData: $customData,
                testEventCode: $settings->tiktok_test_event_code,
                orderId: $order->id,
                affiliateId: $affiliate?->id,
            );
        }
    }

    /**
     * @param  array<string, mixed>  $clientContext
     * @return array<string, mixed>
     */
    protected function extractUserDataFromOrder(Order $order, array $clientContext = []): array
    {
        /** @var OrderAddress|null $shippingAddress */
        $shippingAddress = $order->shippingAddress ?: $order->addresses()->where('type', 'shipping')->first();

        $phoneAttr = $order->getAttribute('customer_phone_e164');
        $addrPhone = $shippingAddress?->getAttribute('contact_phone') ?? $shippingAddress?->getAttribute('phone');
        $phone = $phoneAttr ? (string) $phoneAttr : ($addrPhone ? (string) $addrPhone : null);

        $emailAttr = $order->getAttribute('customer_email');
        $addrEmail = $shippingAddress?->getAttribute('contact_email');
        $email = $emailAttr ? (string) $emailAttr : ($addrEmail ? (string) $addrEmail : null);

        $firstName = $shippingAddress?->getAttribute('first_name') ? (string) $shippingAddress->getAttribute('first_name') : null;
        $lastName = $shippingAddress?->getAttribute('last_name') ? (string) $shippingAddress->getAttribute('last_name') : null;
        $city = $shippingAddress?->getAttribute('city') ? (string) $shippingAddress->getAttribute('city') : null;

        return [
            'email' => $email,
            'phone' => $phone,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'city' => $city,
            'country' => 'eg',
            'client_ip_address' => $clientContext['ip'] ?? request()->ip(),
            'client_user_agent' => $clientContext['user_agent'] ?? request()->userAgent(),
            'fbp' => $clientContext['fbp'] ?? request()->cookie('_fbp'),
            'fbc' => $clientContext['fbc'] ?? request()->cookie('_fbc'),
            'ttclid' => $clientContext['ttclid'] ?? request()->cookie('ttclid'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function extractCustomDataFromOrder(Order $order): array
    {
        $rawTotal = (int) ($order->getRawOriginal('total') ?? 0);
        $decimalTotal = round($rawTotal / 100, 2);

        $contents = [];
        $order->loadMissing('lines.purchasable');

        foreach ($order->lines as $line) {
            /** @var OrderLine $line */
            $purchasable = $line->purchasable;
            $sku = (string) ($purchasable ? ($purchasable->getAttribute('sku') ?? $line->id) : $line->id);
            $rawLineTotal = (int) ($line->getRawOriginal('total') ?? 0);
            $itemPrice = round($rawLineTotal / max($line->quantity, 1) / 100, 2);

            $contents[] = [
                'id' => $sku,
                'content_id' => $sku,
                'quantity' => (int) $line->quantity,
                'item_price' => $itemPrice,
                'price' => $itemPrice,
            ];
        }

        return [
            'currency' => (string) ($order->currency_code ?: 'EGP'),
            'value' => $decimalTotal,
            'order_id' => (string) ($order->customer_reference ?: ($order->reference ?: $order->id)),
            'content_type' => 'product',
            'contents' => $contents,
        ];
    }

    /**
     * @param  array<string, mixed>  $userData
     * @param  array<string, mixed>  $customData
     */
    protected function dispatchOrRun(
        bool $sync,
        string $tenantId,
        string $platform,
        string $pixelId,
        string $accessToken,
        string $eventName,
        string $eventId,
        array $userData,
        array $customData,
        ?string $testEventCode,
        ?int $orderId,
        ?int $affiliateId
    ): void {
        if ($sync) {
            if ($platform === AdConversionEvent::PLATFORM_META) {
                $this->metaService->sendEvent(
                    tenantId: $tenantId,
                    pixelId: $pixelId,
                    accessToken: $accessToken,
                    eventName: $eventName,
                    eventId: $eventId,
                    userData: $userData,
                    customData: $customData,
                    testEventCode: $testEventCode,
                    orderId: $orderId,
                    affiliateId: $affiliateId,
                );
            } elseif ($platform === AdConversionEvent::PLATFORM_TIKTOK) {
                $this->tiktokService->sendEvent(
                    tenantId: $tenantId,
                    pixelId: $pixelId,
                    accessToken: $accessToken,
                    eventName: $eventName,
                    eventId: $eventId,
                    userData: $userData,
                    customData: $customData,
                    testEventCode: $testEventCode,
                    orderId: $orderId,
                    affiliateId: $affiliateId,
                );
            }
        } else {
            DispatchServerConversionJob::dispatch(
                tenantId: $tenantId,
                platform: $platform,
                pixelId: $pixelId,
                accessToken: $accessToken,
                eventName: $eventName,
                eventId: $eventId,
                userData: $userData,
                customData: $customData,
                testEventCode: $testEventCode,
                orderId: $orderId,
                affiliateId: $affiliateId,
            );
        }
    }
}
