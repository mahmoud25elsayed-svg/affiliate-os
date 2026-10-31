<?php

declare(strict_types=1);

namespace App\Domain\Finance\Listeners;

use App\Domain\Affiliate\Services\AffiliateLevelManager;
use App\Domain\Affiliate\Services\AffiliateReferralRewardService;
use App\Domain\Affiliate\Services\QualityScoreCalculator;
use App\Domain\Finance\DTOs\EntryData;
use App\Domain\Finance\DTOs\LedgerLineData;
use App\Domain\Finance\Services\AccountResolver;
use App\Domain\Finance\Services\AffiliateCommissionResolver;
use App\Domain\Finance\Services\Ledger;
use App\Domain\Orders\Enums\OrderStatus;
use App\Domain\Orders\Events\OrderDelivered;
use App\Models\Affiliate;
use Lunar\DataTypes\Price;
use Lunar\Models\Order;

class OrderDeliveredLedgerListener
{
    public function __construct(
        protected Ledger $ledger,
        protected AccountResolver $accountResolver,
        protected QualityScoreCalculator $qualityScoreCalculator,
        protected AffiliateLevelManager $levelManager,
        protected AffiliateReferralRewardService $referralRewardService
    ) {}

    public function handle(OrderDelivered $event): void
    {
        $order = $event->order;

        // AF-11: Record courier COD receivable and store sales revenue upon delivery
        $total = $order->getAttribute('total');
        $orderTotalMinor = $total instanceof Price ? (int) $total->value : (int) $total;
        if ($orderTotalMinor > 0) {
            $codReceivableAccount = $this->accountResolver->getPlatformCodReceivableAccount();
            $storeRevenueAccount = $this->accountResolver->getStoreRevenueAccount();

            $codEntry = new EntryData(
                type: 'order_delivered_cod',
                idempotencyKey: "order_delivered_cod_{$order->id}",
                description: __('مستحقات تحصيل COD وإيراد مبيعات للطلب #:reference', ['reference' => $order->reference]),
                lines: [
                    new LedgerLineData(account: $codReceivableAccount, amountMinor: $orderTotalMinor),
                    new LedgerLineData(account: $storeRevenueAccount, amountMinor: -$orderTotalMinor),
                ],
                refType: Order::class,
                refId: (int) $order->id,
                createdBy: null
            );

            $this->ledger->post($codEntry);
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

        $pendingAccount = $this->accountResolver->getAffiliatePendingAccount($affiliate);
        $expenseAccount = $this->accountResolver->getStoreCommissionExpenseAccount();

        // 1. Post standard commission
        $entryData = new EntryData(
            type: 'order_delivered',
            idempotencyKey: "order_delivered_{$order->id}",
            description: __('استحقاق عمولة معلقة للطلب #:reference', ['reference' => $order->reference]),
            lines: [
                new LedgerLineData(account: $pendingAccount, amountMinor: $profitMinor),
                new LedgerLineData(account: $expenseAccount, amountMinor: -$profitMinor),
            ],
            refType: Order::class,
            refId: (int) $order->id,
            createdBy: null
        );

        $this->ledger->post($entryData);

        // 2. Post Tier Level Bonus if affiliate level has bonus_per_order_minor
        if ($affiliate->level && $affiliate->level->bonus_per_order_minor > 0) {
            $bonusMinor = (int) $affiliate->level->bonus_per_order_minor;
            $bonusExpenseAccount = $this->accountResolver->getStoreBonusExpenseAccount();

            $bonusEntry = new EntryData(
                type: 'tier_bonus',
                idempotencyKey: "order_tier_bonus_{$order->id}_{$affiliate->id}",
                description: __('بونص مستوى :name_ar للطلب #:reference', ['name_ar' => $affiliate->level->name_ar, 'reference' => $order->reference]),
                lines: [
                    new LedgerLineData(account: $pendingAccount, amountMinor: $bonusMinor),
                    new LedgerLineData(account: $bonusExpenseAccount, amountMinor: -$bonusMinor),
                ],
                refType: Order::class,
                refId: (int) $order->id,
                createdBy: null
            );

            $this->ledger->post($bonusEntry);
        }

        // 3. Update cached stats and recalculate score & level (AF-08: count DELIVERED & SETTLED)
        $totalDelivered = Order::query()
            ->where('affiliate_id', $affiliate->id)
            ->whereIn('status', [OrderStatus::DELIVERED->value, OrderStatus::SETTLED->value])
            ->count();

        $affiliate->updateQuietly([
            'total_delivered_orders' => $totalDelivered,
        ]);
        $affiliate->total_delivered_orders = $totalDelivered;

        $stats = $this->qualityScoreCalculator->calculate($affiliate);
        $affiliate->updateQuietly([
            'quality_score' => $stats['quality_score'],
            'daily_order_limit' => $stats['daily_order_limit'],
            'delivery_rate_basis_points' => $stats['delivery_rate_basis_points'],
        ]);
        $affiliate->quality_score = $stats['quality_score'];
        $affiliate->daily_order_limit = $stats['daily_order_limit'];
        $affiliate->delivery_rate_basis_points = $stats['delivery_rate_basis_points'];

        $this->levelManager->evaluateLevel($affiliate, true);

        // 4. Check & award referral reward for referrer
        $this->referralRewardService->checkAndAwardReward($affiliate);
    }
}
