<?php

declare(strict_types=1);

namespace App\Domain\Checkout\DTOs;

readonly class PaymentWebhookResult
{
    /**
     * @param  array<string, mixed>  $rawPayload
     */
    public function __construct(
        public bool $isSuccessful,
        public string $status, // 'paid', 'failed', 'refunded', 'pending'
        public string $transactionRef,
        public ?string $orderReference = null,
        public ?int $amountMinor = null,
        public ?string $currency = 'EGP',
        public ?string $paymentMethod = null,
        public ?string $errorMessage = null,
        public array $rawPayload = [],
        public bool $isPaid = false,
    ) {}

    /**
     * @param  array<string, mixed>  $rawPayload
     */
    public static function paid(
        string $transactionRef,
        ?string $orderReference,
        int $amountMinor,
        string $currency = 'EGP',
        ?string $paymentMethod = null,
        array $rawPayload = []
    ): self {
        return new self(
            isSuccessful: true,
            status: 'paid',
            transactionRef: $transactionRef,
            orderReference: $orderReference,
            amountMinor: $amountMinor,
            currency: $currency,
            paymentMethod: $paymentMethod,
            errorMessage: null,
            rawPayload: $rawPayload,
            isPaid: true
        );
    }

    /**
     * @param  array<string, mixed>  $rawPayload
     */
    public static function failed(
        string $transactionRef,
        ?string $orderReference,
        string $errorMessage,
        array $rawPayload = []
    ): self {
        return new self(
            isSuccessful: false,
            status: 'failed',
            transactionRef: $transactionRef,
            orderReference: $orderReference,
            amountMinor: null,
            currency: null,
            paymentMethod: null,
            errorMessage: $errorMessage,
            rawPayload: $rawPayload,
            isPaid: false
        );
    }
}
