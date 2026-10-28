<?php

declare(strict_types=1);

namespace App\Domain\Affiliate\Services;

use App\Models\Affiliate;
use App\Models\AffiliateLevel;

class AffiliateLevelManager
{
    /**
     * Determine and optionally assign the appropriate level for an affiliate.
     */
    public function evaluateLevel(Affiliate $affiliate, bool $save = true): AffiliateLevel
    {
        $tenantId = $affiliate->tenant_id;

        // Ensure default levels exist for tenant
        if (AffiliateLevel::query()->where('tenant_id', $tenantId)->count() === 0) {
            AffiliateLevel::seedDefaultsForTenant($tenantId);
        }

        $levels = AffiliateLevel::query()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->orderByDesc('tier_order')
            ->get();

        $deliveredOrders = (int) $affiliate->total_delivered_orders;
        $deliveryRateBps = (int) $affiliate->delivery_rate_basis_points;
        $qualityScore = (int) $affiliate->quality_score;

        /** @var AffiliateLevel|null $bestLevel */
        $bestLevel = null;

        foreach ($levels as $level) {
            if (
                $deliveredOrders >= $level->min_delivered_orders &&
                $deliveryRateBps >= $level->min_delivery_rate &&
                $qualityScore >= $level->min_quality_score
            ) {
                $bestLevel = $level;
                break;
            }
        }

        // Fallback to lowest tier (tier_order 1) if none matched
        if ($bestLevel === null) {
            /** @var AffiliateLevel $bestLevel */
            $bestLevel = AffiliateLevel::query()
                ->where('tenant_id', $tenantId)
                ->where('is_active', true)
                ->orderBy('tier_order')
                ->firstOrFail();
        }

        if ($save && $affiliate->affiliate_level_id !== $bestLevel->id) {
            $affiliate->updateQuietly([
                'affiliate_level_id' => $bestLevel->id,
            ]);
            $affiliate->affiliate_level_id = $bestLevel->id;
            $affiliate->setRelation('level', $bestLevel);
        }

        return $bestLevel;
    }
}
