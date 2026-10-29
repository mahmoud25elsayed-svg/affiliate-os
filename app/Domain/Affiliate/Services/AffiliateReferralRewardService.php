<?php

declare(strict_types=1);

namespace App\Domain\Affiliate\Services;

use App\Domain\Affiliate\Settings\AffiliateProgramSettings;
use App\Domain\Finance\DTOs\EntryData;
use App\Domain\Finance\DTOs\LedgerLineData;
use App\Domain\Finance\Services\AccountResolver;
use App\Domain\Finance\Services\Ledger;
use App\Domain\Orders\Enums\OrderStatus;
use App\Models\Affiliate;
use App\Models\AffiliateReferralReward;
use Illuminate\Support\Carbon;
use Lunar\Models\Order;

class AffiliateReferralRewardService
{
    public function __construct(
        protected Ledger $ledger,
        protected AccountResolver $accountResolver
    ) {}

    /**
     * Check if a referred affiliate's performance qualifies the referrer for a referral reward.
     */
    public function checkAndAwardReward(Affiliate $referredAffiliate): ?AffiliateReferralReward
    {
        if ($referredAffiliate->referred_by_id === null) {
            return null;
        }

        $settings = AffiliateProgramSettings::load();
        if (! $settings->isReferralRewardEnabled()) {
            return null;
        }

        /** @var Affiliate|null $referrer */
        $referrer = Affiliate::query()->find($referredAffiliate->referred_by_id);
        if (! $referrer instanceof Affiliate || ! $referrer->isActive()) {
            return null;
        }

        $threshold = $settings->getReferralRewardThreshold();
        $rewardMinor = $settings->getReferralRewardAmountMinor();

        if ($rewardMinor <= 0) {
            return null;
        }

        $deliveredCount = Order::query()
            ->where('affiliate_id', $referredAffiliate->id)
            ->where('status', OrderStatus::DELIVERED->value)
            ->count();

        if ($deliveredCount < $threshold) {
            return null;
        }

        $tenantId = $referrer->tenant_id;

        // Check if already awarded
        $existing = AffiliateReferralReward::query()
            ->where('tenant_id', $tenantId)
            ->where('referrer_id', $referrer->id)
            ->where('referred_id', $referredAffiliate->id)
            ->where('threshold_orders', $threshold)
            ->first();

        if ($existing instanceof AffiliateReferralReward) {
            return $existing;
        }

        // AF-07: Post milestone referral reward to pending balance; settled via CommissionSettlementService upon order settlement
        $referrerPending = $this->accountResolver->getAffiliatePendingAccount($referrer);
        $bonusExpense = $this->accountResolver->getStoreBonusExpenseAccount();

        $idempotencyKey = "aff_referral_reward_{$referrer->id}_{$referredAffiliate->id}_{$threshold}";
        $description = "مكافأة إحالة المسوق #{$referredAffiliate->id} ({$referredAffiliate->name}) بعد إكمال {$threshold} طلبات مسلّمة";

        $entryData = new EntryData(
            type: 'referral_reward',
            idempotencyKey: $idempotencyKey,
            description: $description,
            lines: [
                new LedgerLineData(account: $referrerPending, amountMinor: $rewardMinor),
                new LedgerLineData(account: $bonusExpense, amountMinor: -$rewardMinor),
            ],
            refType: Affiliate::class,
            refId: $referredAffiliate->id,
            createdBy: null
        );

        $journalEntry = $this->ledger->post($entryData);

        /** @var AffiliateReferralReward $reward */
        $reward = AffiliateReferralReward::create([
            'tenant_id' => $tenantId,
            'referrer_id' => $referrer->id,
            'referred_id' => $referredAffiliate->id,
            'threshold_orders' => $threshold,
            'reward_amount_minor' => $rewardMinor,
            'journal_entry_id' => $journalEntry->id,
            'awarded_at' => Carbon::now(),
        ]);

        return $reward;
    }
}
