<?php

declare(strict_types=1);

namespace App\Domain\Affiliate\Services;

use App\Domain\Affiliate\Settings\AffiliateProgramSettings;
use App\Domain\Orders\Enums\OrderStatus;
use App\Models\Affiliate;
use App\Models\OrderEvent;
use Illuminate\Support\Carbon;
use Lunar\Models\Order;

class QualityScoreCalculator
{
    /**
     * Calculate the 30-day rolling quality score and stats for an affiliate.
     *
     * @return array{
     *     quality_score: int,
     *     delivered_count: int,
     *     returned_count: int,
     *     rejected_count: int,
     *     delivery_rate_basis_points: int,
     *     daily_order_limit: int,
     *     reasons: array<string, mixed>
     * }
     */
    public function calculate(Affiliate $affiliate, ?Carbon $asOfDate = null): array
    {
        $date = $asOfDate ?? Carbon::now();
        $startDate = $date->copy()->subDays(30);

        $deliveredCount = Order::query()
            ->where('affiliate_id', $affiliate->id)
            ->whereIn('status', [OrderStatus::DELIVERED->value, OrderStatus::SETTLED->value])
            ->where('created_at', '>=', $startDate)
            ->where('created_at', '<=', $date)
            ->count();

        $returnedCount = Order::query()
            ->where('affiliate_id', $affiliate->id)
            ->whereIn('status', [OrderStatus::RETURNED->value, OrderStatus::DELIVERY_FAILED->value])
            ->where('created_at', '>=', $startDate)
            ->where('created_at', '<=', $date)
            ->count();

        // QualityGate rejections recorded via order events or cancellations
        $rejectedCount = OrderEvent::query()
            ->whereHas('order', function ($query) use ($affiliate) {
                $query->where('affiliate_id', $affiliate->id);
            })
            ->where(function ($q) {
                $q->where('to_status', OrderStatus::REJECTED_QUALITY->value)
                    ->orWhere('reason', 'like', '%QualityGate%');
            })
            ->where('created_at', '>=', $startDate)
            ->where('created_at', '<=', $date)
            ->count();

        $totalCompleted = $deliveredCount + $returnedCount;
        $settings = AffiliateProgramSettings::load();

        // Grace period for new affiliates with fewer than 5 completed orders
        if ($totalCompleted < 5) {
            $deliveryRateBps = 10000;
            $qualityScore = 100;
            $baseLimit = $affiliate->level ? $affiliate->level->daily_order_limit : $settings->getDailyOrderLimit();

            return [
                'quality_score' => $qualityScore,
                'delivered_count' => $deliveredCount,
                'returned_count' => $returnedCount,
                'rejected_count' => $rejectedCount,
                'delivery_rate_basis_points' => $deliveryRateBps,
                'daily_order_limit' => $baseLimit,
                'reasons' => [
                    'notice' => __('فترة سماح للمسوقين الجدد (أقل من 5 طلبات منتهية)'),
                    'delivery_component' => 70,
                    'gate_component' => 20,
                    'volume_component' => 10,
                ],
            ];
        }

        // Delivery component: 0 to 70 points
        $deliveryRate = $deliveredCount / $totalCompleted;
        $deliveryRateBps = (int) round($deliveryRate * 10000);
        $deliveryScore = (int) round($deliveryRate * 70);

        // QualityGate component: 0 to 20 points
        $totalEvaluated = $totalCompleted + $rejectedCount;
        $gateRate = $totalCompleted / $totalEvaluated;
        $gateScore = (int) round($gateRate * 20);

        // Volume factor: 0 to 10 points
        $volumeScore = min(10, $deliveredCount >= 10 ? 10 : $deliveredCount);

        $qualityScore = max(0, min(100, $deliveryScore + $gateScore + $volumeScore));

        // Compute daily order limit
        $baseLimit = $affiliate->level ? $affiliate->level->daily_order_limit : $settings->getDailyOrderLimit();
        if ($qualityScore < $settings->getQualityScoreCriticalThreshold()) {
            $dailyLimit = 0; // Suspended due to low quality
        } elseif ($qualityScore < $settings->getQualityScoreLowThreshold()) {
            $dailyLimit = min(10, (int) max(1, round($baseLimit / 2))); // Throttled
        } else {
            $dailyLimit = $baseLimit;
        }

        return [
            'quality_score' => $qualityScore,
            'delivered_count' => $deliveredCount,
            'returned_count' => $returnedCount,
            'rejected_count' => $rejectedCount,
            'delivery_rate_basis_points' => $deliveryRateBps,
            'daily_order_limit' => $dailyLimit,
            'reasons' => [
                'delivery_component' => $deliveryScore,
                'gate_component' => $gateScore,
                'volume_component' => $volumeScore,
                'delivery_rate_pct' => round($deliveryRate * 100, 1),
            ],
        ];
    }
}
