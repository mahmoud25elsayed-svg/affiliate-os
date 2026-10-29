<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\Checkout\Contracts\OnlinePaymentGatewayInterface;
use App\Domain\Checkout\Services\PaymentManager;
use App\Domain\Orders\DTOs\OrderActor;
use App\Domain\Orders\Enums\OrderStatus;
use App\Domain\Orders\Services\OrderTransitionService;
use App\Http\Controllers\Controller;
use App\Models\PaymentTransaction;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Lunar\Models\Order;

class PaymentWebhookController extends Controller
{
    public function __construct(
        protected PaymentManager $paymentManager,
        protected OrderTransitionService $orderTransitionService
    ) {}

    public function handle(Request $request, string $gateway): JsonResponse
    {
        try {
            $gatewayInstance = $this->paymentManager->get($gateway);
        } catch (\InvalidArgumentException) {
            return response()->json(['error' => "Gateway '{$gateway}' is not supported."], 404);
        }

        if (! $gatewayInstance instanceof OnlinePaymentGatewayInterface) {
            return response()->json(['error' => "Gateway '{$gateway}' does not support online webhooks."], 400);
        }

        $payload = $request->all();

        // Attempt tenant resolution if running outside tenant domain
        $this->resolveTenantContext($request, $payload);

        // Extract cryptographic signature
        /** @var string $signature */
        $signature = (string) (
            $request->query('hmac')
            ?? $request->header('X-Paymob-Signature')
            ?? $request->header('X-Signature')
            ?? $request->input('hmac')
            ?? $request->input('signature')
            ?? ''
        );

        if (empty($signature) || ! $gatewayInstance->verifyWebhookSignature($payload, $signature)) {
            Log::warning("Invalid payment webhook signature received for gateway [{$gateway}]", [
                'ip' => $request->ip(),
                'gateway' => $gateway,
            ]);

            return response()->json(['error' => 'Invalid webhook signature.'], 401);
        }

        $result = $gatewayInstance->handleWebhook($payload);

        // Idempotency check: if transaction is already marked paid, return success without duplicate processing
        $existingTxn = PaymentTransaction::query()
            ->where('transaction_ref', $result->transactionRef)
            ->first();

        if ($existingTxn && $existingTxn->status === PaymentTransaction::STATUS_PAID) {
            return response()->json([
                'status' => 'ignored',
                'message' => 'Transaction already processed successfully.',
                'transaction_ref' => $result->transactionRef,
            ], 200);
        }

        // Locate corresponding Order
        $order = null;
        if ($result->orderReference !== null && $result->orderReference !== '') {
            $order = Order::query()->where('reference', $result->orderReference)->first();
        }

        if (! $order && $existingTxn && $existingTxn->order_id) {
            $order = Order::query()->find($existingTxn->order_id);
        }

        $status = $result->isPaid ? PaymentTransaction::STATUS_PAID : PaymentTransaction::STATUS_FAILED;
        $tenantId = tenant('id');
        $tenantIdString = is_string($tenantId) ? $tenantId : (string) ($order?->getAttribute('tenant_id') ?? 'default');

        // Persist payment transaction
        $txn = $existingTxn ?? new PaymentTransaction;
        $txn->tenant_id = $tenantIdString;
        $txn->order_id = $order?->id;
        $txn->gateway = $gateway;
        $txn->transaction_ref = $result->transactionRef;
        $txn->status = $status;
        $txn->amount_minor = $result->amountMinor > 0 ? $result->amountMinor : ($existingTxn->amount_minor ?? 0);
        $txn->currency = $result->currency ?: ($order->currency_code ?? 'EGP');
        $txn->payment_method = $result->paymentMethod;
        $txn->gateway_payload = array_merge($txn->gateway_payload ?? [], [
            'webhook_received_at' => now()->toIso8601String(),
            'raw' => $result->rawPayload,
        ]);
        $txn->error_message = $result->errorMessage;
        if ($result->isPaid) {
            $txn->paid_at = now();
        }
        $txn->save();

        // If payment succeeded and order is awaiting confirmation, transition to confirmed
        if ($result->isPaid && $order instanceof Order) {
            $this->advanceOrderOnPayment($order, $gateway, $result->transactionRef);
        }

        return response()->json([
            'status' => 'success',
            'paid' => $result->isPaid,
            'transaction_ref' => $result->transactionRef,
        ], 200);
    }

    /**
     * Resolves and initializes tenancy securely from the referenced order or transaction (SEC-14).
     *
     * @param  array<string, mixed>  $payload
     */
    protected function resolveTenantContext(Request $request, array $payload): void
    {
        if (function_exists('tenancy') && tenancy()->initialized) {
            return;
        }

        $tenantId = null;

        // 1. Resolve tenant deterministically from Order reference (immutable truth)
        $orderRef = $payload['order_reference'] ?? $payload['obj']['order']['merchant_order_id'] ?? null;
        if (is_string($orderRef) && $orderRef !== '') {
            $order = Order::withoutGlobalScopes()->where('reference', $orderRef)->first();
            if ($order && $order->getAttribute('tenant_id')) {
                $tenantId = (string) $order->getAttribute('tenant_id');
            }
        }

        // 2. Resolve from payment transaction if not found from order reference
        if (! $tenantId) {
            $txnRef = $payload['transaction_id'] ?? $payload['obj']['id'] ?? null;
            if ($txnRef) {
                $txn = PaymentTransaction::withoutGlobalScopes()
                    ->where('transaction_ref', (string) $txnRef)
                    ->first();
                if ($txn && $txn->tenant_id) {
                    $tenantId = (string) $txn->tenant_id;
                }
            }
        }

        // 3. Fallback to header only if internal trusted gateway dispatch
        if (! $tenantId) {
            $tenantId = $request->header('X-Tenant-Id');
        }

        if (is_string($tenantId) && $tenantId !== '') {
            $tenant = Tenant::query()->find($tenantId);
            if ($tenant instanceof Tenant) {
                tenancy()->initialize($tenant);
            }
        }
    }

    /**
     * Transition order forward upon successful online payment.
     */
    protected function advanceOrderOnPayment(Order $order, string $gateway, string $transactionRef): void
    {
        $currentStatus = OrderStatus::tryFrom((string) $order->status);
        $actor = OrderActor::system();

        try {
            if ($currentStatus === OrderStatus::SUBMITTED) {
                $this->orderTransitionService->transition(
                    $order,
                    OrderStatus::PENDING_CONFIRMATION,
                    null,
                    $actor,
                    ['gateway' => $gateway, 'txn' => $transactionRef],
                    'الدفع الإلكتروني قيد المراجعة'
                );
                $currentStatus = OrderStatus::PENDING_CONFIRMATION;
            }

            if ($currentStatus === OrderStatus::PENDING_CONFIRMATION) {
                $this->orderTransitionService->transition(
                    $order,
                    OrderStatus::CONFIRMED,
                    null,
                    $actor,
                    ['gateway' => $gateway, 'txn' => $transactionRef],
                    "تم تأكيد الدفع الإلكتروني بنجاح عبر {$gateway}"
                );
            }
        } catch (\Throwable $e) {
            Log::error("Failed to auto-transition order [{$order->id}] after payment: ".$e->getMessage());
        }
    }
}
