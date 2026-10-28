<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Affiliate\Services\AffiliateLevelManager;
use App\Domain\Affiliate\Services\QualityScoreCalculator;
use App\Models\Affiliate;
use App\Models\AffiliateQualityLog;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class CalculateAffiliateDailyQualityScoresCommand extends Command
{
    protected $signature = 'affiliate:calculate-daily-quality-scores {--date= : The date to calculate for (Y-m-d)}';

    protected $description = 'Calculates 30-day rolling quality scores, daily limits, and level evaluations for affiliates';

    public function handle(
        QualityScoreCalculator $calculator,
        AffiliateLevelManager $levelManager
    ): int {
        $dateStr = $this->option('date');
        $date = $dateStr ? Carbon::parse((string) $dateStr) : Carbon::today();

        $this->info("Calculating affiliate quality scores as of {$date->toDateString()}...");

        $affiliates = Affiliate::query()
            ->where('status', Affiliate::STATUS_ACTIVE)
            ->get();

        $count = 0;
        foreach ($affiliates as $affiliate) {
            $stats = $calculator->calculate($affiliate, $date);

            // Record daily log
            AffiliateQualityLog::updateOrCreate(
                [
                    'tenant_id' => $affiliate->tenant_id,
                    'affiliate_id' => $affiliate->id,
                    'calculated_date' => $date->toDateString(),
                ],
                [
                    'quality_score' => $stats['quality_score'],
                    'delivered_count' => $stats['delivered_count'],
                    'returned_count' => $stats['returned_count'],
                    'rejected_count' => $stats['rejected_count'],
                    'delivery_rate' => $stats['delivery_rate_basis_points'],
                    'daily_order_limit' => $stats['daily_order_limit'],
                    'affiliate_level_id' => $affiliate->affiliate_level_id,
                    'reasons' => $stats['reasons'],
                ]
            );

            // Update affiliate model
            $affiliate->updateQuietly([
                'quality_score' => $stats['quality_score'],
                'daily_order_limit' => $stats['daily_order_limit'],
                'delivery_rate_basis_points' => $stats['delivery_rate_basis_points'],
            ]);
            $affiliate->quality_score = $stats['quality_score'];
            $affiliate->daily_order_limit = $stats['daily_order_limit'];
            $affiliate->delivery_rate_basis_points = $stats['delivery_rate_basis_points'];

            // Re-evaluate level
            $level = $levelManager->evaluateLevel($affiliate, true);

            $this->line("Affiliate #{$affiliate->id} ({$affiliate->name}): Score {$stats['quality_score']}, Level {$level->name_ar}, Limit {$stats['daily_order_limit']}");
            $count++;
        }

        $this->info("Successfully calculated quality scores for {$count} active affiliates.");

        return Command::SUCCESS;
    }
}
