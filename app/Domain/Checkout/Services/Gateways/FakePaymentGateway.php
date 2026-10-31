<?php

declare(strict_types=1);

namespace App\Domain\Checkout\Services\Gateways;

use App\Domain\Checkout\Contracts\OnlinePaymentGatewayInterface;
use App\Domain\Checkout\DTOs\PaymentResult;
use App\Domain\Checkout\DTOs\PaymentWebhookResult;
use App\Domain\Checkout\DTOs\RefundResult;
use App\Models\PaymentTransaction;
use Lunar\Models\Order;

class FakePaymentGateway implements OnlinePaymentGatewayInterface
{
    public function getIdentifier(): string
    {
        return 'fake';
    }

    public function getNameAr(): string
    {
        return __('بوابة دفع تجريبية (Fake Gateway)');
    }

    public function process(Order $order, array $paymentData = []): PaymentResult
    {
        /** @var mixed $total */
        $total = $order->getAttribute('total');
        $amountMinor = is_object($total) && isset($total->value) ? (int) $total->value : (int) ($total ?? 0);
        $transactionRef = 'FAKE-TXN-'.strtoupper(bin2hex(random_bytes(6)));

        PaymentTransaction::create([
            'order_id' => $order->id,
            'gateway' => $this->getIdentifier(),
            'transaction_ref' => $transactionRef,
            'status' => PaymentTransaction::STATUS_PENDING,
            'amount_minor' => $amountMinor,
            'currency' => $order->currency_code ?? 'EGP',
            'payment_method' => $paymentData['method'] ?? 'card',
        ]);

        $redirectUrl = route('storefront.checkout.success', ['reference' => $order->reference]).'?txn='.$transactionRef;

        return PaymentResult::redirect($redirectUrl, $transactionRef, [
            'mode' => 'sandbox',
            'order_reference' => $order->reference,
        ]);
    }

    public function verifyWebhookSignature(array $payload, string $signature, ?string $secretKey = null): bool
    {
        $secret = $secretKey ?? 'fake-secret-key';
        $expected = hash_hmac('sha256', (string) json_encode($payload), $secret);

        return hash_equals($expected, $signature);
    }

    public function handleWebhook(array $payload): PaymentWebhookResult
    {
        $txnRef = (string) ($payload['transaction_ref'] ?? 'UNKNOWN');
        $orderRef = isset($payload['order_reference']) ? (string) $payload['order_reference'] : null;
        $isSuccess = (bool) ($payload['success'] ?? true);
        $amount = (int) ($payload['amount_minor'] ?? 0);

        if ($isSuccess) {
            return PaymentWebhookResult::paid(
                transactionRef: $txnRef,
                orderReference: $orderRef,
                amountMinor: $amount,
                currency: (string) ($payload['currency'] ?? 'EGP'),
                paymentMethod: (string) ($payload['payment_method'] ?? 'card'),
                rawPayload: $payload
            );
        }

        return PaymentWebhookResult::failed(
            transactionRef: $txnRef,
            orderReference: $orderRef,
            errorMessage: (string) ($payload['error'] ?? 'Fake payment failed'),
            rawPayload: $payload
        );
    }

    public function refund(string $transactionRef, int $amountMinor, ?string $reason = null): RefundResult
    {
        $refundRef = 'FAKE-REF-'.strtoupper(bin2hex(random_bytes(6)));

        return RefundResult::success(
            refundRef: $refundRef,
            amountRefundedMinor: $amountMinor,
            rawResponse: [
                'transaction_ref' => $transactionRef,
                'reason' => $reason,
                'status' => 'refunded',
            ]
        );
    }
}
