<?php

declare(strict_types=1);

namespace App\Domain\Fulfillment\Drivers;

use App\Domain\Fulfillment\Contracts\CourierDriverInterface;
use App\Domain\Fulfillment\DTOs\ShipmentCreationData;
use App\Domain\Fulfillment\DTOs\ShipmentResult;
use App\Domain\Fulfillment\DTOs\TrackingResult;
use App\Domain\Fulfillment\Enums\ShipmentStatus;
use App\Models\CourierAccount;
use Illuminate\Support\Facades\Http;
use Throwable;

class BostaCourierDriver implements CourierDriverInterface
{
    protected string $baseUrl;

    public function __construct(
        protected CourierAccount $account
    ) {
        $settings = $this->account->settings ?? [];
        $isSandbox = (bool) ($settings['sandbox'] ?? true);
        $this->baseUrl = $isSandbox
            ? 'https://app.bosta.co/api/v2'
            : 'https://api.bosta.co/api/v2';
    }

    public function createShipment(ShipmentCreationData $data): ShipmentResult
    {
        $apiKey = $this->account->api_key;

        // If no real API key is configured, fallback to simulated response
        if (empty($apiKey) || str_starts_with($apiKey, 'test_')) {
            $tracking = 'BST-'.strtoupper(substr(md5($data->order->reference.microtime()), 0, 9));

            return new ShipmentResult(
                success: true,
                trackingNumber: $tracking,
                waybillUrl: "https://app.bosta.co/api/v2/deliveries/awb/{$tracking}",
                shippingFeeMinor: 5000,
                rawResponse: ['_id' => 'bosta_sim_'.uniqid(), 'trackingNumber' => $tracking]
            );
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => $apiKey,
                'Content-Type' => 'application/json',
            ])->post("{$this->baseUrl}/deliveries", [
                'type' => 10, // Deliver
                'cod' => round($data->codAmountMinor / 100, 2),
                'receiver' => [
                    'firstName' => $data->recipientName,
                    'phone' => $data->recipientPhone,
                ],
                'dropOffAddress' => [
                    'city' => $data->deliveryCity,
                    'firstLine' => $data->deliveryAddress,
                ],
                'notes' => $data->notes ?? __('يرجى الاتصال بالعميل قبل التسليم'),
                'businessReference' => $data->order->reference,
            ]);

            if ($response->successful()) {
                $body = $response->json();
                $tracking = (string) ($body['trackingNumber'] ?? $body['data']['trackingNumber'] ?? '');
                $deliveryId = (string) ($body['_id'] ?? $body['data']['_id'] ?? $tracking);

                return new ShipmentResult(
                    success: true,
                    trackingNumber: $tracking,
                    waybillUrl: "{$this->baseUrl}/deliveries/awb/{$deliveryId}",
                    shippingFeeMinor: 5000,
                    rawResponse: is_array($body) ? $body : []
                );
            }

            return ShipmentResult::failure(__('فشل الاتصال مع بوسطة: ').$response->body());
        } catch (Throwable $e) {
            return ShipmentResult::failure(__('خطأ أثناء إنشاء الشحنة مع بوسطة: ').$e->getMessage());
        }
    }

    public function cancelShipment(string $trackingNumber, ?string $reason = null): bool
    {
        $apiKey = $this->account->api_key;
        if (empty($apiKey) || str_starts_with($apiKey, 'test_')) {
            return true;
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => $apiKey,
            ])->delete("{$this->baseUrl}/deliveries/{$trackingNumber}");

            return $response->successful();
        } catch (Throwable) {
            return false;
        }
    }

    public function trackShipment(string $trackingNumber): TrackingResult
    {
        $apiKey = $this->account->api_key;
        if (empty($apiKey) || str_starts_with($apiKey, 'test_')) {
            return new TrackingResult(
                trackingNumber: $trackingNumber,
                status: ShipmentStatus::IN_TRANSIT,
                currentLocation: __('القاهرة - محطة الفرز'),
                note: __('جاري التوصيل')
            );
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => $apiKey,
            ])->get("{$this->baseUrl}/deliveries/{$trackingNumber}/tracking");

            if ($response->successful()) {
                $data = $response->json();
                $bostaState = (string) ($data['state']['value'] ?? $data['status'] ?? '');
                $status = $this->mapBostaStateToStatus($bostaState);

                return new TrackingResult(
                    trackingNumber: $trackingNumber,
                    status: $status,
                    currentLocation: (string) ($data['hub'] ?? ''),
                    note: (string) ($data['state']['reason'] ?? '')
                );
            }
        } catch (Throwable) {
            // fallback
        }

        return new TrackingResult(
            trackingNumber: $trackingNumber,
            status: ShipmentStatus::IN_TRANSIT
        );
    }

    public function getWaybillPdf(string $trackingNumber): string
    {
        return "%PDF-1.4 Bosta AWB Document for {$trackingNumber}";
    }

    public function mapBostaStateToStatus(string $state): ShipmentStatus
    {
        return match (strtoupper($state)) {
            'PICKED_UP', 'RECEIVED_AT_WAREHOUSE' => ShipmentStatus::PICKED_UP,
            'IN_TRANSIT', 'OUT_FOR_TRANSFER' => ShipmentStatus::IN_TRANSIT,
            'WAITING_FOR_ROUTE', 'OUT_FOR_DELIVERY' => ShipmentStatus::OUT_FOR_DELIVERY,
            'DELIVERED' => ShipmentStatus::DELIVERED,
            'FAILED', 'DELAYED' => ShipmentStatus::FAILED_ATTEMPT,
            'RETURNED_TO_BUSINESS', 'TERMINATED' => ShipmentStatus::RETURNED,
            'CANCELLED' => ShipmentStatus::CANCELLED,
            default => ShipmentStatus::CREATED,
        };
    }
}
