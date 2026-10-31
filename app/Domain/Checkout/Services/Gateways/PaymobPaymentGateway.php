<?php

declare(strict_types=1);

namespace App\Domain\Checkout\Services\Gateways;

use App\Domain\Checkout\Contracts\OnlinePaymentGatewayInterface;
use App\Domain\Checkout\DTOs\PaymentResult;
use App\Domain\Checkout\DTOs\PaymentWebhookResult;
use App\Domain\Checkout\DTOs\RefundResult;
use App\Models\PaymentTransaction;
use App\Models\TenantPaymentGateway;
use Illuminate\Support\Facades\Http;
use Lunar\Models\Order;

class PaymobPaymentGateway implements OnlinePaymentGatewayInterface
{
    public const IDENTIFIER = 'paymob';

    public function getIdentifier(): string
    {
        return self::IDENTIFIER;
    }

    public function getNameAr(): string
    {
        return __('باي موب (Paymob — كروت ومحافظ إلكترونية)');
    }

    public function process(Order $order, array $paymentData = []): PaymentResult
    {
        $gatewayConfig = $this->getTenantConfig();
        /** @var mixed $total */
        $total = $order->getAttribute('total');
        $amountMinor = is_object($total) && isset($total->value) ? (int) $total->value : (int) ($total ?? 0);
        $transactionRef = 'PMB-'.strtoupper(bin2hex(random_bytes(6)));

        PaymentTransaction::create([
            'order_id' => $order->id,
            'gateway' => $this->getIdentifier(),
            'transaction_ref' => $transactionRef,
            'status' => PaymentTransaction::STATUS_PENDING,
            'amount_minor' => $amountMinor,
            'currency' => $order->currency_code ?? 'EGP',
            'payment_method' => $paymentData['method'] ?? 'card',
        ]);

        $iframeId = $gatewayConfig ? (string) $gatewayConfig->getCredential('iframe_id', '12345') : '12345';
        $paymentToken = 'mock_token_'.bin2hex(random_bytes(16));
        $iframeUrl = sprintf('https://accept.paymob.com/api/acceptance/iframes/%s?payment_token=%s', $iframeId, $paymentToken);
        $redirectUrl = route('storefront.checkout.success', ['reference' => $order->reference]).'?txn='.$transactionRef;

        return PaymentResult::iframe($iframeUrl, $transactionRef, [
            'redirect_url' => $redirectUrl,
            'payment_token' => $paymentToken,
            'order_reference' => $order->reference,
        ]);
    }

    /**
     * Paymob official HMAC SHA-512 signature verification.
     * Extracts values alphabetically by key and computes hash_hmac.
     *
     * @param  array<string, mixed>  $payload
     */
    public function verifyWebhookSignature(array $payload, string $signature, ?string $secretKey = null): bool
    {
        $secret = $secretKey ?? $this->getHmacSecret();
        if (empty($secret)) {
            return false;
        }

        // Paymob sends transaction data nested in 'obj'
        /** @var array<string, mixed> $obj */
        $obj = isset($payload['obj']) && is_array($payload['obj']) ? $payload['obj'] : $payload;

        $concatenated = $this->buildPaymobConcatenatedString($obj);
        $expectedHmac = hash_hmac('sha512', $concatenated, $secret);

        return hash_equals($expectedHmac, $signature);
    }

    /**
     * Build Paymob's concatenated string in the exact order required by the documentation.
     *
     * @param  array<string, mixed>  $obj
     */
    public function buildPaymobConcatenatedString(array $obj): string
    {
        $orderId = '';
        if (isset($obj['order']) && is_array($obj['order']) && isset($obj['order']['id'])) {
            $orderId = (string) $obj['order']['id'];
        } elseif (isset($obj['order_id'])) {
            $orderId = (string) $obj['order_id'];
        }

        $sourceDataPan = '';
        $sourceDataSubType = '';
        $sourceDataType = '';
        if (isset($obj['source_data']) && is_array($obj['source_data'])) {
            $sourceDataPan = (string) ($obj['source_data']['pan'] ?? '');
            $sourceDataSubType = (string) ($obj['source_data']['sub_type'] ?? '');
            $sourceDataType = (string) ($obj['source_data']['type'] ?? '');
        }

        $toScalar = function (mixed $val): string {
            if (is_bool($val)) {
                return $val ? 'true' : 'false';
            }

            return (string) ($val ?? '');
        };

        // Paymob strictly requires values of these 20 fields alphabetically concatenated:
        return
            $toScalar($obj['amount_cents'] ?? '')
            .$toScalar($obj['created_at'] ?? '')
            .$toScalar($obj['currency'] ?? '')
            .$toScalar($obj['error_occured'] ?? '')
            .$toScalar($obj['has_parent_transaction'] ?? '')
            .$toScalar($obj['id'] ?? '')
            .$toScalar($obj['integration_id'] ?? '')
            .$toScalar($obj['is_3d_secure'] ?? '')
            .$toScalar($obj['is_auth'] ?? '')
            .$toScalar($obj['is_capture'] ?? '')
            .$toScalar($obj['is_refunded'] ?? '')
            .$toScalar($obj['is_standalone_payment'] ?? '')
            .$toScalar($obj['is_voided'] ?? '')
            .$orderId
            .$toScalar($obj['owner'] ?? '')
            .$toScalar($obj['pending'] ?? '')
            .$sourceDataPan
            .$sourceDataSubType
            .$sourceDataType
            .$toScalar($obj['success'] ?? '');
    }

    public function handleWebhook(array $payload): PaymentWebhookResult
    {
        /** @var array<string, mixed> $obj */
        $obj = isset($payload['obj']) && is_array($payload['obj']) ? $payload['obj'] : $payload;

        $txnId = (string) ($obj['id'] ?? '');
        $isSuccess = filter_var($obj['success'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $isPending = filter_var($obj['pending'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $amountCents = (int) ($obj['amount_cents'] ?? 0);
        $currency = (string) ($obj['currency'] ?? 'EGP');

        $orderRef = null;
        if (isset($obj['order']) && is_array($obj['order'])) {
            $orderRef = isset($obj['order']['merchant_order_id'])
                ? (string) $obj['order']['merchant_order_id']
                : (string) ($obj['order']['id'] ?? '');
        }

        $paymentMethod = 'card';
        if (isset($obj['source_data']) && is_array($obj['source_data'])) {
            $paymentMethod = (string) ($obj['source_data']['type'] ?? 'card');
        }

        if ($isSuccess && ! $isPending) {
            return PaymentWebhookResult::paid(
                transactionRef: $txnId,
                orderReference: $orderRef,
                amountMinor: $amountCents,
                currency: $currency,
                paymentMethod: $paymentMethod,
                rawPayload: $payload
            );
        }

        $error = (string) ($obj['data']['message'] ?? $obj['error_occured'] ?? 'Payment failed or cancelled');

        return PaymentWebhookResult::failed(
            transactionRef: $txnId,
            orderReference: $orderRef,
            errorMessage: $error,
            rawPayload: $payload
        );
    }

    public function refund(string $transactionRef, int $amountMinor, ?string $reason = null): RefundResult
    {
        $gatewayConfig = $this->getTenantConfig();
        $apiKey = $gatewayConfig ? (string) $gatewayConfig->getCredential('api_key') : '';

        // If credentials are live, call Paymob API; otherwise mock
        if (! empty($apiKey) && $apiKey !== 'mock') {
            try {
                $response = Http::post('https://accept.paymob.com/api/acceptance/void_refund/refund', [
                    'auth_token' => $apiKey,
                    'transaction_id' => $transactionRef,
                    'amount_cents' => $amountMinor,
                ]);

                if ($response->successful()) {
                    /** @var array<string, mixed> $data */
                    $data = $response->json();
                    $refundId = (string) ($data['id'] ?? 'REF-'.uniqid());

                    return RefundResult::success($refundId, $amountMinor, $data);
                }

                return RefundResult::failure((string) ($response->json()['message'] ?? 'Paymob refund rejected'));
            } catch (\Throwable $e) {
                return RefundResult::failure($e->getMessage());
            }
        }

        // Mock refund for development/testing
        $refundId = 'PMB-REF-'.strtoupper(bin2hex(random_bytes(6)));

        return RefundResult::success($refundId, $amountMinor, [
            'mock' => true,
            'transaction_id' => $transactionRef,
            'reason' => $reason,
        ]);
    }

    protected function getTenantConfig(): ?TenantPaymentGateway
    {
        return TenantPaymentGateway::query()
            ->where('gateway', self::IDENTIFIER)
            ->where('is_enabled', true)
            ->first();
    }

    protected function getHmacSecret(): ?string
    {
        $config = $this->getTenantConfig();
        if ($config) {
            return (string) $config->getCredential('hmac_secret');
        }

        return config('services.paymob.hmac_secret');
    }
}
