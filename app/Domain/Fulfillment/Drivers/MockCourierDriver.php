<?php

declare(strict_types=1);

namespace App\Domain\Fulfillment\Drivers;

use App\Domain\Fulfillment\Contracts\CourierDriverInterface;
use App\Domain\Fulfillment\DTOs\ShipmentCreationData;
use App\Domain\Fulfillment\DTOs\ShipmentResult;
use App\Domain\Fulfillment\DTOs\TrackingResult;
use App\Domain\Fulfillment\Enums\ShipmentStatus;
use App\Models\CourierAccount;
use Illuminate\Support\Carbon;

class MockCourierDriver implements CourierDriverInterface
{
    /**
     * @var array<string, ShipmentStatus>
     */
    public static array $mockStatusOverrides = [];

    public function __construct(
        protected CourierAccount $account
    ) {}

    public function createShipment(ShipmentCreationData $data): ShipmentResult
    {
        $hash = strtoupper(substr(md5($data->order->reference.microtime()), 0, 8));
        $trackingNumber = "MCK-EGY-{$hash}";
        $waybillUrl = "https://mockcourier.localhost/awb/{$trackingNumber}.pdf";

        return new ShipmentResult(
            success: true,
            trackingNumber: $trackingNumber,
            waybillUrl: $waybillUrl,
            shippingFeeMinor: 4500, // 45.00 EGP standard mock fee
            rawResponse: [
                'status' => 'created',
                'tracking_number' => $trackingNumber,
                'waybill_url' => $waybillUrl,
                'created_at' => Carbon::now()->toIso8601String(),
                'provider' => 'mock_courier',
            ]
        );
    }

    public function cancelShipment(string $trackingNumber, ?string $reason = null): bool
    {
        self::$mockStatusOverrides[$trackingNumber] = ShipmentStatus::CANCELLED;

        return true;
    }

    public function trackShipment(string $trackingNumber): TrackingResult
    {
        $status = self::$mockStatusOverrides[$trackingNumber] ?? ShipmentStatus::IN_TRANSIT;

        return new TrackingResult(
            trackingNumber: $trackingNumber,
            status: $status,
            currentLocation: 'مركز توزيع القاهرة الرئيسي',
            note: 'الشحنة في المسار الطبيعي',
            events: [
                [
                    'status' => 'created',
                    'timestamp' => Carbon::now()->subHours(5)->toIso8601String(),
                    'description' => 'تم إنشاء بوليصة الشحن',
                ],
                [
                    'status' => $status->value,
                    'timestamp' => Carbon::now()->toIso8601String(),
                    'description' => $status->label(),
                ],
            ]
        );
    }

    public function getWaybillPdf(string $trackingNumber): string
    {
        return "%PDF-1.4 Mock Waybill for {$trackingNumber}\nRecipient Shipping Label";
    }
}
