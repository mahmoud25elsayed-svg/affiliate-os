<?php

declare(strict_types=1);

namespace App\Domain\Inventory\DTOs;

final class StockReservationItem
{
    public function __construct(
        public readonly int $variantId,
        public readonly int $quantity,
        public readonly ?int $warehouseId = null
    ) {}
}
