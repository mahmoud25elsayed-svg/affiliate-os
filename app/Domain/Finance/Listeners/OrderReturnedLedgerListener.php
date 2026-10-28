<?php

declare(strict_types=1);

namespace App\Domain\Finance\Listeners;

use App\Domain\Finance\DTOs\EntryData;
use App\Domain\Finance\DTOs\LedgerLineData;
use App\Domain\Finance\Services\AccountResolver;
use App\Domain\Finance\Services\AffiliateCommissionResolver;
use App\Domain\Finance\Services\Ledger;
use App\Domain\Orders\Events\OrderReturned;
use App\Models\Affiliate;
use App\Models\JournalEntry;
use Lunar\Models\Order;

class OrderReturnedLedgerListener
{
    public function __construct(
        protected Ledger $ledger,
        protected AccountResolver $accountResolver
    ) {}

    public function handle(OrderReturned $event): void
    {
        $order = $event->order;
        $affiliateId = $order->getAttribute('affiliate_id');

        if ($affiliateId === null) {
            return;
        }

        $profitMinor = AffiliateCommissionResolver::getCommissionMinor($order);
        if ($profitMinor <= 0) {
            return;
        }

        /** @var Affiliate|null $affiliate */
        $affiliate = Affiliate::find($affiliateId);
        if (! $affiliate instanceof Affiliate) {
            return;
        }

        $expenseAccount = $this->accountResolver->getStoreCommissionExpenseAccount();
        $tenantId = tenant('id') ? (string) tenant('id') : 'central';

        // Check if settlement entry exists for this order
        $isSettled = JournalEntry::query()
            ->where('tenant_id', $tenantId)
            ->where('idempotency_key', "order_settled_{$order->id}")
            ->exists();

        if ($isSettled) {
            // Late return after settlement: deduct from available (can cause negative balance / debt)
            $availableAccount = $this->accountResolver->getAffiliateAvailableAccount($affiliate);

            $entryData = new EntryData(
                type: 'order_returned_late',
                idempotencyKey: "order_late_return_reversal_{$order->id}",
                description: "عكس عمولة مرتجع متأخر بعد التسوية للطلب #{$order->reference}",
                lines: [
                    new LedgerLineData(account: $availableAccount, amountMinor: -$profitMinor),
                    new LedgerLineData(account: $expenseAccount, amountMinor: $profitMinor),
                ],
                refType: Order::class,
                refId: (int) $order->id,
                createdBy: null
            );

            $this->ledger->post($entryData);
        } else {
            // Check if delivery entry was posted
            $isDelivered = JournalEntry::query()
                ->where('tenant_id', $tenantId)
                ->where('idempotency_key', "order_delivered_{$order->id}")
                ->exists();

            if ($isDelivered) {
                // Return during pending window: deduct from pending
                $pendingAccount = $this->accountResolver->getAffiliatePendingAccount($affiliate);

                $entryData = new EntryData(
                    type: 'order_returned',
                    idempotencyKey: "order_return_reversal_{$order->id}",
                    description: "إلغاء عمولة معلقة لمرتجع الطلب #{$order->reference}",
                    lines: [
                        new LedgerLineData(account: $pendingAccount, amountMinor: -$profitMinor),
                        new LedgerLineData(account: $expenseAccount, amountMinor: $profitMinor),
                    ],
                    refType: Order::class,
                    refId: (int) $order->id,
                    createdBy: null
                );

                $this->ledger->post($entryData);
            }
        }
    }
}
