<?php

declare(strict_types=1);

namespace App\Support\Billing\DTOs;

class WebhookResult
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public readonly bool $handled,
        public readonly string $event,
        public readonly ?string $subscriptionId = null,
        public readonly ?string $transactionId = null,
        public readonly ?int $amount = null,
        public readonly ?string $status = null,
        public readonly array $payload = []
    ) {}
}
