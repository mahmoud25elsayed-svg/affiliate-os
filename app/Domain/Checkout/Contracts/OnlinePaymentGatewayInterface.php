<?php

declare(strict_types=1);

namespace App\Domain\Checkout\Contracts;

use App\Domain\Checkout\DTOs\PaymentWebhookResult;
use App\Domain\Checkout\DTOs\RefundResult;

interface OnlinePaymentGatewayInterface extends PaymentGatewayInterface
{
    /**
     * Verify the cryptographic signature of incoming webhook requests.
     *
     * @param  array<string, mixed>  $payload
     */
    public function verifyWebhookSignature(array $payload, string $signature, ?string $secretKey = null): bool;

    /**
     * Process an incoming verified webhook payload into a standardized result.
     *
     * @param  array<string, mixed>  $payload
     */
    public function handleWebhook(array $payload): PaymentWebhookResult;

    /**
     * Execute a full or partial refund.
     */
    public function refund(string $transactionRef, int $amountMinor, ?string $reason = null): RefundResult;
}
