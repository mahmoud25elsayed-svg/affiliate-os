<?php

declare(strict_types=1);

namespace App\Http\Controllers\Affiliate\App;

use App\Domain\Affiliate\Services\AffiliateLevelManager;
use App\Domain\Affiliate\Services\QualityScoreCalculator;
use App\Domain\Affiliate\Settings\AffiliateProgramSettings;
use App\Http\Controllers\Controller;
use App\Models\Affiliate;
use App\Models\AffiliateLevel;
use App\Models\AffiliateReferralReward;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use Lunar\Models\Order;

class AppLevelsController extends Controller
{
    public function index(
        Request $request,
        QualityScoreCalculator $calculator,
        AffiliateLevelManager $levelManager
    ): Response {
        /** @var Affiliate $affiliate */
        $affiliate = Auth::guard('affiliate')->user();
        $tenantId = $affiliate->tenant_id;

        // Ensure default levels exist
        if (AffiliateLevel::query()->where('tenant_id', $tenantId)->count() === 0) {
            AffiliateLevel::seedDefaultsForTenant($tenantId);
        }

        $currentLevel = $levelManager->evaluateLevel($affiliate, true);
        $settings = AffiliateProgramSettings::load();
        $qualityStats = $calculator->calculate($affiliate);

        // Fetch all levels in order
        $allLevels = AffiliateLevel::query()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->orderBy('tier_order')
            ->get()
            ->map(fn (AffiliateLevel $l) => [
                'id' => $l->id,
                'name' => $l->name_ar,
                'slug' => $l->slug,
                'tier_order' => $l->tier_order,
                'badge_color' => $l->badge_color,
                'min_delivered_orders' => $l->min_delivered_orders,
                'min_delivery_rate' => $l->min_delivery_rate / 100, // as percentage e.g. 70.0%
                'min_quality_score' => $l->min_quality_score,
                'bonus_per_order_minor' => $l->bonus_per_order_minor,
                'commission_bonus_percent' => $l->commission_bonus_percent,
                'daily_order_limit' => $l->daily_order_limit,
                'is_current' => $l->id === $currentLevel->id,
                'is_unlocked' => $l->tier_order <= $currentLevel->tier_order,
            ]);

        // Next level calculation
        /** @var AffiliateLevel|null $nextLevel */
        $nextLevel = AffiliateLevel::query()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->where('tier_order', '>', $currentLevel->tier_order)
            ->orderBy('tier_order')
            ->first();

        $nextLevelData = null;
        if ($nextLevel) {
            $remainingOrders = max(0, $nextLevel->min_delivered_orders - $affiliate->total_delivered_orders);
            $nextLevelData = [
                'id' => $nextLevel->id,
                'name' => $nextLevel->name_ar,
                'badge_color' => $nextLevel->badge_color,
                'remaining_orders' => $remainingOrders,
                'target_delivered_orders' => $nextLevel->min_delivered_orders,
                'target_delivery_rate' => $nextLevel->min_delivery_rate / 100,
                'target_quality_score' => $nextLevel->min_quality_score,
                'bonus_per_order_minor' => $nextLevel->bonus_per_order_minor,
                'commission_bonus_percent' => $nextLevel->commission_bonus_percent,
                'daily_order_limit' => $nextLevel->daily_order_limit,
            ];
        }

        // Referrals list and stats
        $referralThreshold = $settings->getReferralRewardThreshold();
        $referralRewardMinor = $settings->getReferralRewardAmountMinor();

        $referrals = Affiliate::query()
            ->where('referred_by_id', $affiliate->id)
            ->latest('id')
            ->get()
            ->map(function (Affiliate $ref) use ($referralThreshold, $referralRewardMinor, $affiliate) {
                $delivered = Order::query()
                    ->where('affiliate_id', $ref->id)
                    ->where('status', 'delivered')
                    ->count();

                $isRewardUnlocked = $delivered >= $referralThreshold;
                $rewardRecorded = AffiliateReferralReward::query()
                    ->where('referrer_id', $affiliate->id)
                    ->where('referred_id', $ref->id)
                    ->exists();

                return [
                    'id' => $ref->id,
                    'name' => $ref->name,
                    'code' => $ref->code,
                    'joined_at' => $ref->created_at?->format('Y-m-d') ?? '',
                    'delivered_orders' => $delivered,
                    'target_orders' => $referralThreshold,
                    'is_unlocked' => $isRewardUnlocked,
                    'reward_earned_minor' => $rewardRecorded ? $referralRewardMinor : 0,
                ];
            });

        $totalReferralRewardsEarnedMinor = AffiliateReferralReward::query()
            ->where('referrer_id', $affiliate->id)
            ->sum('reward_amount_minor');

        return Inertia::render('App/Levels', [
            'currentLevel' => [
                'id' => $currentLevel->id,
                'name' => $currentLevel->name_ar,
                'slug' => $currentLevel->slug,
                'badge_color' => $currentLevel->badge_color,
                'tier_order' => $currentLevel->tier_order,
                'bonus_per_order_minor' => $currentLevel->bonus_per_order_minor,
                'commission_bonus_percent' => $currentLevel->commission_bonus_percent,
                'daily_order_limit' => $affiliate->daily_order_limit,
            ],
            'nextLevel' => $nextLevelData,
            'allLevels' => $allLevels,
            'stats' => [
                'quality_score' => $affiliate->quality_score,
                'rating' => $affiliate->getQualityScoreRating(),
                'total_delivered_orders' => $affiliate->total_delivered_orders,
                'delivery_rate_pct' => round($affiliate->delivery_rate_basis_points / 100, 1),
                'reasons' => $qualityStats['reasons'],
            ],
            'referralProgram' => [
                'is_enabled' => $settings->isReferralRewardEnabled(),
                'referral_code' => $affiliate->code,
                'invite_url' => url("/partners/register?ref={$affiliate->code}"),
                'threshold_orders' => $referralThreshold,
                'reward_amount_minor' => $referralRewardMinor,
                'total_earnings_minor' => (int) $totalReferralRewardsEarnedMinor,
                'referrals_count' => $referrals->count(),
                'referrals' => $referrals,
            ],
        ]);
    }
}
