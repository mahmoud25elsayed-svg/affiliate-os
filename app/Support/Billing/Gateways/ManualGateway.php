<?php

declare(strict_types=1);

namespace App\Support\Billing\Gateways;

use App\Models\Subscription;
use App\Models\SubscriptionInvoice;
use App\Support\Billing\Contracts\BillingGateway;
use App\Support\Billing\DTOs\PaymentResult;
use App\Support\Billing\DTOs\WebhookResult;
use App\Support\Money;

class ManualGateway implements BillingGateway
{
    public function name(): string
    {
        return 'manual';
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function initiatePayment(Subscription $subscription, Money $amount, array $payload = []): PaymentResult
    {
        $year = now()->year;
        $count = SubscriptionInvoice::whereYear('created_at', $year)->count() + 1;
        $invoiceNumber = sprintf('INV-%d-%05d', $year, $count);

        $startsAt = $payload['billing_period_starts_at'] ?? now();
        $endsAt = $payload['billing_period_ends_at'] ?? ($subscription->billing_cycle === Subscription::CYCLE_YEARLY ? now()->addYear() : now()->addMonth());

        /** @var SubscriptionInvoice $invoice */
        $invoice = SubscriptionInvoice::create([
            'invoice_number' => $invoiceNumber,
            'subscription_id' => $subscription->id,
            'target_plan_id' => $payload['target_plan_id'] ?? null,
            'target_cycle' => $payload['target_cycle'] ?? null,
            'tenant_id' => $subscription->tenant_id,
            'amount' => $amount->amount,
            'currency' => $amount->currency,
            'status' => SubscriptionInvoice::STATUS_PENDING,
            'billing_period_starts_at' => $startsAt,
            'billing_period_ends_at' => $endsAt,
            'due_at' => now()->addDays(3),
            'payment_method' => $payload['payment_method'] ?? 'manual_bank_transfer',
            'payment_reference' => $payload['payment_reference'] ?? null,
            'receipt_path' => $payload['receipt_path'] ?? null,
            'notes' => $payload['notes'] ?? null,
        ]);

        $subscription->events()->create([
            'tenant_id' => $subscription->tenant_id,
            'event' => 'payment_pending',
            'actor_type' => $payload['actor_type'] ?? 'user',
            'actor_id' => $payload['actor_id'] ?? null,
            'metadata' => [
                'invoice_number' => $invoice->invoice_number,
                'amount' => $amount->amount,
                'currency' => $amount->currency,
                'method' => $invoice->payment_method,
            ],
            'created_at' => now(),
        ]);

        return new PaymentResult(
            success: true,
            transactionId: $invoice->invoice_number,
            message: __('تم استلام طلب التجديد والإيصال بنجاح وجارٍ مراجعته من الإدارة.'),
            data: ['invoice_id' => $invoice->id, 'invoice_number' => $invoice->invoice_number]
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handleWebhook(array $payload): WebhookResult
    {
        return new WebhookResult(
            handled: false,
            event: 'unsupported',
            payload: $payload
        );
    }
}
