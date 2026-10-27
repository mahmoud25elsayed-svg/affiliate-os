<?php

declare(strict_types=1);

namespace App\Support\Billing\Services;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SubscriptionInvoice;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Billing\Gateways\ManualGateway;
use InvalidArgumentException;

class SubscriptionService
{
    public function __construct(
        protected ManualGateway $manualGateway
    ) {}

    public function startTrial(Tenant $tenant, Plan $plan, ?int $days = null): Subscription
    {
        $days = $days ?? $plan->trial_days;

        /** @var Subscription $subscription */
        $subscription = $tenant->subscriptions()->create([
            'plan_id' => $plan->id,
            'status' => Subscription::STATUS_TRIAL,
            'billing_cycle' => Subscription::CYCLE_MONTHLY,
            'trial_ends_at' => now()->addDays($days),
            'current_period_starts_at' => now(),
            'current_period_ends_at' => now()->addDays($days),
        ]);

        $subscription->events()->create([
            'tenant_id' => $tenant->id,
            'event' => 'trial_started',
            'metadata' => [
                'plan' => $plan->slug,
                'trial_days' => $days,
            ],
            'created_at' => now(),
        ]);

        return $subscription;
    }

    /**
     * @param  array{payment_method?: string, payment_reference?: string, receipt_path?: string, notes?: string, actor_id?: string}  $data
     */
    public function requestManualRenewal(Subscription $subscription, ?Plan $plan = null, ?string $cycle = null, array $data = []): SubscriptionInvoice
    {
        $plan = $plan ?? $subscription->plan;
        $cycle = $cycle ?? $subscription->billing_cycle;

        if ($plan->id !== $subscription->plan_id || $cycle !== $subscription->billing_cycle) {
            $subscription->update([
                'plan_id' => $plan->id,
                'billing_cycle' => $cycle,
            ]);
        }

        $amount = $cycle === Subscription::CYCLE_YEARLY
            ? $plan->priceYearlyMoney()
            : $plan->priceMonthlyMoney();

        $result = $this->manualGateway->initiatePayment($subscription, $amount, array_merge($data, [
            'payment_gateway' => 'manual',
        ]));

        /** @var int $invoiceId */
        $invoiceId = $result->data['invoice_id'];

        return SubscriptionInvoice::findOrFail($invoiceId);
    }

    public function approveInvoice(SubscriptionInvoice $invoice, User $admin): void
    {
        if (! $invoice->isPending()) {
            throw new InvalidArgumentException(__('هذه الفاتورة ليست قيد الانتظار.'));
        }

        $invoice->update([
            'status' => SubscriptionInvoice::STATUS_PAID,
            'paid_at' => now(),
            'approved_by' => $admin->id,
        ]);

        /** @var Subscription $subscription */
        $subscription = $invoice->subscription;
        $now = now();
        $isYearly = $subscription->billing_cycle === Subscription::CYCLE_YEARLY;

        $startsAt = ($subscription->isActive() && $subscription->current_period_ends_at && $subscription->current_period_ends_at->isFuture())
            ? $subscription->current_period_ends_at
            : $now;

        $endsAt = $isYearly ? $startsAt->copy()->addYear() : $startsAt->copy()->addMonth();

        $subscription->update([
            'status' => Subscription::STATUS_ACTIVE,
            'current_period_starts_at' => $startsAt,
            'current_period_ends_at' => $endsAt,
            'grace_period_ends_at' => null,
            'cancelled_at' => null,
            'payment_gateway' => 'manual',
        ]);

        // If tenant was suspended, restore tenant immediately!
        $tenant = $subscription->tenant;
        if ($tenant->isSuspended()) {
            $tenant->update(['status' => 'active']);
        }

        $subscription->events()->create([
            'tenant_id' => $subscription->tenant_id,
            'event' => 'payment_approved',
            'actor_type' => 'admin',
            'actor_id' => (string) $admin->id,
            'metadata' => [
                'invoice_number' => $invoice->invoice_number,
                'amount' => $invoice->amount,
                'starts_at' => $startsAt->toIso8601String(),
                'ends_at' => $endsAt->toIso8601String(),
            ],
            'created_at' => now(),
        ]);
    }

    public function rejectInvoice(SubscriptionInvoice $invoice, User $admin, string $reason): void
    {
        if (! $invoice->isPending()) {
            throw new InvalidArgumentException(__('هذه الفاتورة ليست قيد الانتظار.'));
        }

        $invoice->update([
            'status' => SubscriptionInvoice::STATUS_REJECTED,
            'admin_notes' => $reason,
            'approved_by' => $admin->id,
        ]);

        $subscription = $invoice->subscription;

        $subscription->events()->create([
            'tenant_id' => $subscription->tenant_id,
            'event' => 'payment_rejected',
            'actor_type' => 'admin',
            'actor_id' => (string) $admin->id,
            'metadata' => [
                'invoice_number' => $invoice->invoice_number,
                'reason' => $reason,
            ],
            'created_at' => now(),
        ]);
    }

    public function cancelSubscription(Subscription $subscription, ?string $reason = null): void
    {
        $subscription->update([
            'status' => Subscription::STATUS_CANCELLED,
            'cancelled_at' => now(),
        ]);

        $subscription->events()->create([
            'tenant_id' => $subscription->tenant_id,
            'event' => 'cancelled',
            'metadata' => ['reason' => $reason],
            'created_at' => now(),
        ]);
    }
}
