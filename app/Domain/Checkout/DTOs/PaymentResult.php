<?php

declare(strict_types=1);

namespace App\Domain\Checkout\DTOs;

readonly class PaymentResult
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public bool $isSuccessful,
        public string $status, // 'pending', 'paid', 'failed'
        public ?string $transactionRef = null,
        public ?string $redirectUrl = null,
        public ?string $iframeUrl = null,
        public ?string $errorMessage = null,
        public array $metadata = [],
        public bool $isRedirect = false,
        public bool $isIframe = false,
    ) {}

    public static function cod(): self
    {
        return new self(
            isSuccessful: true,
            status: 'pending',
            transactionRef: 'COD-'.strtoupper(bin2hex(random_bytes(4))),
            redirectUrl: null,
            iframeUrl: null,
            errorMessage: null
        );
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    public static function redirect(string $url, string $transactionRef, array $metadata = []): self
    {
        return new self(
            isSuccessful: true,
            status: 'pending',
            transactionRef: $transactionRef,
            redirectUrl: $url,
            iframeUrl: null,
            errorMessage: null,
            metadata: $metadata,
            isRedirect: true,
            isIframe: false
        );
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    public static function iframe(string $url, string $transactionRef, array $metadata = []): self
    {
        return new self(
            isSuccessful: true,
            status: 'pending',
            transactionRef: $transactionRef,
            redirectUrl: null,
            iframeUrl: $url,
            errorMessage: null,
            metadata: $metadata,
            isRedirect: false,
            isIframe: true
        );
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    public static function success(string $transactionRef, array $metadata = []): self
    {
        return new self(
            isSuccessful: true,
            status: 'paid',
            transactionRef: $transactionRef,
            redirectUrl: null,
            iframeUrl: null,
            errorMessage: null,
            metadata: $metadata,
            isRedirect: false,
            isIframe: false
        );
    }

    public static function failure(string $message): self
    {
        return new self(
            isSuccessful: false,
            status: 'failed',
            transactionRef: null,
            redirectUrl: null,
            iframeUrl: null,
            errorMessage: $message,
            metadata: [],
            isRedirect: false,
            isIframe: false
        );
    }
}
