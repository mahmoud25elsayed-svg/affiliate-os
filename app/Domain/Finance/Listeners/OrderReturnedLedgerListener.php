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
use Lunar\DataTypes\Price;
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
        $tenantId = tenant('id') ? (string) tenant('id') : 'central';

        // AF-11: Reverse COD receivable and sales revenue if order was returned
        $codDeliveredEntry = JournalEntry::query()
            ->where('tenant_id', $tenantId)
            ->where('idempotency_key', "order_delivered_cod_{$order->id}")
            ->exists();

        if ($codDeliveredEntry) {
            $total = $order->getAttribute('total');
            $orderTotalMinor = $total instanceof Price ? (int) $total->value : (int) $total;
            if ($orderTotalMinor > 0) {
                $codReceivableAccount = $this->accountResolver->getPlatformCodReceivableAccount();
                $storeRevenueAccount = $this->accountResolver->getStoreRevenueAccount();

                $codReversalEntry = new EntryData(
                    type: 'order_returned_cod',
                    idempotencyKey: "order_returned_cod_{$order->id}",
                    description: __('إلغاء مستحقات تحصيل COD وإيراد المبيعات لمرتجع الطلب #:reference', ['reference' => $order->reference]),
                    lines: [
                        new LedgerLineData(account: $codReceivableAccount, amountMinor: -$orderTotalMinor),
                        new LedgerLineData(account: $storeRevenueAccount, amountMinor: $orderTotalMinor),
                    ],
                    refType: Order::class,
                    refId: (int) $order->id,
                    createdBy: null
                );

                $this->ledger->post($codReversalEntry);
            }
        }

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
                description: __('عكس عمولة مرتجع متأخر بعد التسوية للطلب #:reference', ['reference' => $order->reference]),
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
                    description: __('إلغاء عمولة معلقة لمرتجع الطلب #:reference', ['reference' => $order->reference]),
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

        // AF-06: Reverse Tier Level Bonus if one was awarded for this order
        $bonusEntry = JournalEntry::query()
            ->where('tenant_id', $tenantId)
            ->where('idempotency_key', "order_tier_bonus_{$order->id}_{$affiliate->id}")
            ->first();

        if ($bonusEntry) {
            $pendingAccount = $this->accountResolver->getAffiliatePendingAccount($affiliate);
            $availableAccount = $this->accountResolver->getAffiliateAvailableAccount($affiliate);
            $bonusExpenseAccount = $this->accountResolver->getStoreBonusExpenseAccount();

            $bonusMinor = (int) $bonusEntry->lines()
                ->where('account_id', $pendingAccount->id)
                ->sum('amount_minor');

            if ($bonusMinor > 0) {
                $isBonusSettled = JournalEntry::query()
                    ->where('tenant_id', $tenantId)
                    ->where('idempotency_key', "order_tier_bonus_settled_{$order->id}_{$affiliate->id}")
                    ->exists();

                $targetAccount = $isBonusSettled ? $availableAccount : $pendingAccount;
                $bonusReversalType = $isBonusSettled ? 'tier_bonus_returned_late' : 'tier_bonus_returned';

                $reversalEntry = new EntryData(
                    type: $bonusReversalType,
                    idempotencyKey: "order_tier_bonus_reversal_{$order->id}_{$affiliate->id}",
                    description: __('إلغاء بونص مستوى المسوق لمرتجع الطلب #:reference', ['reference' => $order->reference]),
                    lines: [
                        new LedgerLineData(account: $targetAccount, amountMinor: -$bonusMinor),
                        new LedgerLineData(account: $bonusExpenseAccount, amountMinor: $bonusMinor),
                    ],
                    refType: Order::class,
                    refId: (int) $order->id,
                    createdBy: null
                );

                $this->ledger->post($reversalEntry);
            }
        }
    }
}
