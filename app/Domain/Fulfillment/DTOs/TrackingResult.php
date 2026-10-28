<?php

declare(strict_types=1);

namespace App\Domain\Fulfillment\DTOs;

use App\Domain\Fulfillment\Enums\ShipmentStatus;

readonly class TrackingResult
{
    /**
     * @param  list<array{status: string, timestamp: string, description: string}>  $events
     */
    public function __construct(
        public string $trackingNumber,
        public ShipmentStatus $status,
        public ?string $currentLocation = null,
        public ?string $note = null,
        public array $events = []
    ) {}
}

