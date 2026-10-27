<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Exceptions;

class InsufficientStockException extends InventoryException
{
    public function __construct(
        public readonly int $variantId,
        public readonly int $requestedQuantity,
        public readonly int $availableQuantity,
        ?string $message = null
    ) {
        $msg = $message ?? sprintf(
            'Insufficient stock for product variant #%d. Requested: %d, Available: %d.',
            $this->variantId,
            $this->requestedQuantity,
            $this->availableQuantity
        );

        parent::__construct($msg);
    }
}
