<?php

declare(strict_types=1);

namespace App\Domain\Finance\Listeners;

use App\Domain\Finance\DTOs\EntryData;
use App\Domain\Finance\DTOs\LedgerLineData;
use App\Domain\Finance\Services\AccountResolver;
use App\Domain\Finance\Services\AffiliateCommissionResolver;
use App\Domain\Finance\Services\Ledger;
use App\Domain\Orders\Events\OrderDelivered;
use App\Models\Affiliate;
use Lunar\Models\Order;

class OrderDeliveredLedgerListener
{
    public function __construct(
        protected Ledger $ledger,
        protected AccountResolver $accountResolver
    ) {}

    public function handle(OrderDelivered $event): void
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

        $pendingAccount = $this->accountResolver->getAffiliatePendingAccount($affiliate);
        $expenseAccount = $this->accountResolver->getStoreCommissionExpenseAccount();

        $entryData = new EntryData(
            type: 'order_delivered',
            idempotencyKey: "order_delivered_{$order->id}",
            description: "استحقاق عمولة معلقة للطلب #{$order->reference}",
            lines: [
                new LedgerLineData(account: $pendingAccount, amountMinor: $profitMinor),
                new LedgerLineData(account: $expenseAccount, amountMinor: -$profitMinor),
            ],
            refType: Order::class,
            refId: (int) $order->id,
            createdBy: null
        );

        $this->ledger->post($entryData);
    }
}
