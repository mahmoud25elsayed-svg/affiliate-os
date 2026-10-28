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

        return $this->ledger->post($entryData);
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
            ->where('updated_at', '<=', $cutoff)
            ->get();

        $settledCount = 0;
        $actor = OrderActor::system();

        foreach ($orders as $order) {
            // Check if already settled
            $alreadySettled = JournalEntry::query()
                ->where('idempotency_key', "order_settled_{$order->id}")
                ->exists();

            if (! $alreadySettled) {
                // Transition order to settled
                $order = $this->transitionService->transition(
                    order: $order,
                    to: OrderStatus::SETTLED,
                    reason: null,
                    actor: $actor,
                    meta: [],
                    note: 'تسوية أوتوماتيكية بعد انتهاء نافذة المرتجع المقررة.'
                );

                $this->settleOrder($order);
                $settledCount++;
            }
        }

        return $settledCount;
    }
}
