<?php

declare(strict_types=1);

namespace App\Domain\Finance\Gateways;

final readonly class PayoutGatewayResult
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        public bool $success,
        public ?string $referenceNumber = null,
        public ?string $message = null,
        public array $data = []
    ) {}

    public static function successful(string $referenceNumber, string $message = 'تم التحويل بنجاح'): self
    {
        return new self(
            success: true,
            referenceNumber: $referenceNumber,
            message: $message,
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function failed(string $message, array $data = []): self
    {
        return new self(
            success: false,
            message: $message,
            data: $data,
        );
    }
}
