<?php

declare(strict_types=1);

namespace App\Domain\Finance\Services;

use App\Domain\Affiliate\Settings\AffiliateProgramSettings;
use App\Domain\Finance\DTOs\EntryData;
use App\Domain\Finance\DTOs\LedgerLineData;
use App\Domain\Orders\DTOs\OrderActor;
use App\Domain\Orders\Enums\OrderStatus;
use App\Domain\Orders\Services\OrderTransitionService;
use App\Models\Affiliate;
use App\Models\JournalEntry;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Lunar\Models\Order;

class CommissionSettlementService
{
    public function __construct(
        protected Ledger $ledger,
        protected AccountResolver $accountResolver,
        protected OrderTransitionService $transitionService
    ) {}

    /**
     * Settle a delivered order's commission by moving it from pending to available.
     */
    public function settleOrder(Order $order): ?JournalEntry
    {
        $affiliateId = $order->getAttribute('affiliate_id');
        if ($affiliateId === null) {
            return null;
        }

        $profitMinor = AffiliateCommissionResolver::getCommissionMinor($order);
        if ($profitMinor <= 0) {
            return null;
        }

        /** @var Affiliate|null $affiliate */
        $affiliate = Affiliate::find($affiliateId);
        if (! $affiliate instanceof Affiliate) {
            return null;
        }

        $pendingAccount = $this->accountResolver->getAffiliatePendingAccount($affiliate);
        $availableAccount = $this->accountResolver->getAffiliateAvailableAccount($affiliate);

        $entryData = new EntryData(
            type: 'commission_settled',
            idempotencyKey: "order_settled_{$order->id}",
            description: "تسوية عمولة الطلب #{$order->reference} ونقلها إلى الرصيد المتاح للسحب",
            lines: [
                new LedgerLineData(account: $pendingAccount, amountMinor: -$profitMinor),
                new LedgerLineData(account: $availableAccount, amountMinor: $profitMinor),
            ],
            refType: Order::class,
            refId: (int) $order->id,
            createdBy: null
        );

        $settledEntry = $this->ledger->post($entryData);

        // AF-05: Settle Tier Level Bonus if posted for this order
        $tenantId = tenant('id') ? (string) tenant('id') : 'central';
        $bonusEntry = JournalEntry::query()
            ->where('tenant_id', $tenantId)
            ->where('idempotency_key', "order_tier_bonus_{$order->id}_{$affiliate->id}")
            ->first();

        if ($bonusEntry) {
            $bonusMinor = (int) $bonusEntry->lines()
                ->where('account_id', $pendingAccount->id)
                ->sum('amount_minor');

            if ($bonusMinor > 0) {
                $bonusSettlement = new EntryData(
                    type: 'tier_bonus_settled',
                    idempotencyKey: "order_tier_bonus_settled_{$order->id}_{$affiliate->id}",
                    description: "تسوية بونص مستوى المسوق للطلب #{$order->reference} ونقلها إلى الرصيد المتاح",
                    lines: [
                        new LedgerLineData(account: $pendingAccount, amountMinor: -$bonusMinor),
                        new LedgerLineData(account: $availableAccount, amountMinor: $bonusMinor),
                    ],
                    refType: Order::class,
                    refId: (int) $order->id,
                    createdBy: null
                );
                $this->ledger->post($bonusSettlement);
            }
        }

        // AF-07: Settle referral reward if awarded for this affiliate
        if ($affiliate->referred_by_id) {
            $referrer = Affiliate::find($affiliate->referred_by_id);
            if ($referrer instanceof Affiliate) {
                $referralEntries = JournalEntry::query()
                    ->where('tenant_id', $tenantId)
                    ->where('type', 'referral_reward')
                    ->where('ref_type', Affiliate::class)
                    ->where('ref_id', $affiliate->id)
                    ->get();

                foreach ($referralEntries as $refRewardEntry) {
                    $settlementKey = "settled_{$refRewardEntry->idempotency_key}";
                    $isRewardSettled = JournalEntry::query()
                        ->where('tenant_id', $tenantId)
                        ->where('idempotency_key', $settlementKey)
                        ->exists();

                    if (! $isRewardSettled) {
                        $refPending = $this->accountResolver->getAffiliatePendingAccount($referrer);
                        $refAvailable = $this->accountResolver->getAffiliateAvailableAccount($referrer);
                        $amount = (int) $refRewardEntry->lines()
                            ->where('account_id', $refPending->id)
                            ->sum('amount_minor');

                        if ($amount > 0) {
                            $this->ledger->post(new EntryData(
                                type: 'referral_reward_settled',
                                idempotencyKey: $settlementKey,
                                description: "تسوية مكافأة إحالة المسوق #{$affiliate->id} ونقلها للرصيد المتاح",
                                lines: [
                                    new LedgerLineData(account: $refPending, amountMinor: -$amount),
                                    new LedgerLineData(account: $refAvailable, amountMinor: $amount),
                                ],
                                refType: Affiliate::class,
                                refId: $affiliate->id,
                                createdBy: null
                            ));
                        }
                    }
                }
            }
        }

        return $settledEntry;
    }

    /**
     * Settle all delivered orders whose return window has elapsed.
     */
    public function settleEligibleDeliveredOrders(?int $windowDaysOverride = null): int
    {
        $settings = AffiliateProgramSettings::load();
        $windowDays = $windowDaysOverride ?? $settings->getReturnWindowDays();
        $cutoff = Carbon::now()->subDays($windowDays);

        /** @var Collection<int, Order> $orders */
        $orders = Order::query()
            ->whereNotNull('affiliate_id')
            ->where('status', OrderStatus::DELIVERED->value)
            ->where(function ($query) use ($cutoff) {
                // AF-16: Check actual delivery event / journal entry timestamp, fallback to updated_at
                $query->whereExists(function ($sub) use ($cutoff) {
                    $sub->select(\Illuminate\Support\Facades\DB::raw(1))
                        ->from('journal_entries')
                        ->whereColumn('journal_entries.ref_id', 'lunar_orders.id')
                        ->where('journal_entries.type', 'order_delivered')
                        ->where('journal_entries.created_at', '<=', $cutoff);
                })->orWhere(function ($fallback) use ($cutoff) {
                    $fallback->whereNotExists(function ($sub) {
                        $sub->select(\Illuminate\Support\Facades\DB::raw(1))
                            ->from('journal_entries')
                            ->whereColumn('journal_entries.ref_id', 'lunar_orders.id')
                            ->where('journal_entries.type', 'order_delivered');
                    })->where('lunar_orders.updated_at', '<=', $cutoff);
                });
            })
            ->get();

        $settledCount = 0;
        $actor = OrderActor::system();

        foreach ($orders as $order) {
            // Check if already settled
            $alreadySettled = JournalEntry::query()
                ->where('idempotency_key', "order_settled_{$order->id}")
                ->exists();

            if (! $alreadySettled) {
                // AF-16: Execute transition and ledger settlement in a single atomic database transaction
                \Illuminate\Support\Facades\DB::transaction(function () use ($order, $actor) {
                    $transitionedOrder = $this->transitionService->transition(
                        order: $order,
                        to: OrderStatus::SETTLED,
                        reason: null,
                        actor: $actor,
                        meta: [],
                        note: 'تسوية أوتوماتيكية بعد انتهاء نافذة المرتجع المقررة.'
                    );

                    $this->settleOrder($transitionedOrder);
                });
                $settledCount++;
            }
        }

        return $settledCount;
    }
}
