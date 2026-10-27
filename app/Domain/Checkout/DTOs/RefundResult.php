<?php

declare(strict_types=1);

namespace App\Domain\Checkout\DTOs;

readonly class RefundResult
{
    /**
     * @param  array<string, mixed>  $rawResponse
     */
    public function __construct(
        public bool $isSuccessful,
        public ?string $refundRef = null,
        public int $amountRefundedMinor = 0,
        public ?string $errorMessage = null,
        public array $rawResponse = [],
    ) {}

    /**
     * @param  array<string, mixed>  $rawResponse
     */
    public static function success(string $refundRef, int $amountRefundedMinor, array $rawResponse = []): self
    {
        return new self(
            isSuccessful: true,
            refundRef: $refundRef,
            amountRefundedMinor: $amountRefundedMinor,
            errorMessage: null,
            rawResponse: $rawResponse
        );
    }

    public static function failure(string $message): self
    {
        return new self(
            isSuccessful: false,
            refundRef: null,
            amountRefundedMinor: 0,
            errorMessage: $message,
            rawResponse: []
        );
    }
}
