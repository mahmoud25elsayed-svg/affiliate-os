<?php

declare(strict_types=1);

namespace App\Domain\Fulfillment\DTOs;

use Lunar\Models\Order;

readonly class ShipmentCreationData
{
    public function __construct(
        public Order $order,
        public string $recipientName,
        public string $recipientPhone,
        public string $deliveryGovernorate,
        public string $deliveryCity,
        public string $deliveryAddress,
        public int $codAmountMinor,
        public int $declaredValueMinor = 0,
        public int $itemsCount = 1,
        public string $description = 'شحنة متجر إلكتروني',
        public ?string $notes = null
    ) {}
}

