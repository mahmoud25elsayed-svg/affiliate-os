<?php

declare(strict_types=1);

namespace App\Domain\Fulfillment\Contracts;

use App\Domain\Fulfillment\DTOs\ShipmentCreationData;
use App\Domain\Fulfillment\DTOs\ShipmentResult;
use App\Domain\Fulfillment\DTOs\TrackingResult;

interface CourierDriverInterface
{
    /**
     * Dispatch an order and create a new shipment record with the courier.
     */
    public function createShipment(ShipmentCreationData $data): ShipmentResult;

    /**
     * Cancel an active shipment with the courier.
     */
    public function cancelShipment(string $trackingNumber, ?string $reason = null): bool;

    /**
     * Query live tracking status from the courier API.
     */
    public function trackShipment(string $trackingNumber): TrackingResult;

    /**
     * Retrieve the printable waybill / AWB document (PDF binary or HTML).
     */
    public function getWaybillPdf(string $trackingNumber): string;
}

