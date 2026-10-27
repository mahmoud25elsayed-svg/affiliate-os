<?php

declare(strict_types=1);

namespace App\Support\Billing\DTOs;

class PaymentResult
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        public readonly bool $success,
        public readonly ?string $transactionId = null,
        public readonly ?string $redirectUrl = null,
        public readonly ?string $message = null,
        public readonly array $data = []
    ) {}
}
