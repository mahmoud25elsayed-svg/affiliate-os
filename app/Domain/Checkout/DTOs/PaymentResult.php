<?php

declare(strict_types=1);

namespace App\Domain\Checkout\DTOs;

readonly class PaymentResult
{
    public function __construct(
        public bool $isSuccessful,
        public string $status, // 'pending', 'paid', 'failed'
        public ?string $transactionRef = null,
        public ?string $redirectUrl = null,
        public ?string $errorMessage = null,
    ) {}

    public static function cod(): self
    {
        return new self(
            isSuccessful: true,
            status: 'pending',
            transactionRef: 'COD-'.strtoupper(bin2hex(random_bytes(4))),
            redirectUrl: null,
            errorMessage: null
        );
    }

    public static function failure(string $message): self
    {
        return new self(
            isSuccessful: false,
            status: 'failed',
            transactionRef: null,
            redirectUrl: null,
            errorMessage: $message
        );
    }
}
