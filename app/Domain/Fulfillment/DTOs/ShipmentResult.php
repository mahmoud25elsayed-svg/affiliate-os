<?php

declare(strict_types=1);

namespace App\Domain\Fulfillment\DTOs;

readonly class ShipmentResult
{
    /**
     * @param  array<string, mixed>  $rawResponse
     */
    public function __construct(
        public bool $success,
        public string $trackingNumber,
        public ?string $waybillUrl = null,
        public int $shippingFeeMinor = 4500,
        public array $rawResponse = [],
        public ?string $errorMessage = null
    ) {}

    public static function failure(string $errorMessage): self
    {
        return new self(
            success: false,
            trackingNumber: '',
            waybillUrl: null,
            shippingFeeMinor: 0,
            rawResponse: [],
            errorMessage: $errorMessage
        );
    }
}

