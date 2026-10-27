<?php

declare(strict_types=1);

namespace App\Support\Billing\Contracts;

use App\Models\Subscription;
use App\Support\Billing\DTOs\PaymentResult;
use App\Support\Billing\DTOs\WebhookResult;
use App\Support\Money;

interface BillingGateway
{
    public function name(): string;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function initiatePayment(Subscription $subscription, Money $amount, array $payload = []): PaymentResult;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handleWebhook(array $payload): WebhookResult;
}
