<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Affiliate\Services\AffiliateTrackingService;
use Illuminate\Console\Command;

class PruneAffiliateClicksCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'affiliate:prune-clicks {--days=90 : The number of days of clicks to retain}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Prune old affiliate click tracking records older than the retention threshold';

    /**
     * Execute the console command.
     */
    public function handle(AffiliateTrackingService $trackingService): int
    {
        $days = (int) $this->option('days');
        if ($days <= 0) {
            $days = 90;
        }

        $this->info("جاري حذف نقرات التتبع الأقدم من {$days} يوماً...");

        $deletedCount = $trackingService->pruneOldClicks($days);

        $this->info("تم بنجاح حذف {$deletedCount} سجلاً من نقرات التتبع القديمة.");

        return self::SUCCESS;
    }
}
