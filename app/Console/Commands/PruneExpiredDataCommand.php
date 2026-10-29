<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PruneExpiredDataCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'compliance:prune-expired-data
                            {--clicks-days=90 : Days to retain affiliate clicks}
                            {--events-days=180 : Days to retain pixel and ad conversion events}
                            {--webhooks-days=30 : Days to retain webhook delivery logs}
                            {--notifications-days=90 : Days to retain notification dispatch logs}
                            {--dry-run : Simulate pruning without deleting records}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Prune outdated tracking clicks, ad conversion events, webhook deliveries, and logs per data retention policy';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $clicksDays = max(1, (int) $this->option('clicks-days'));
        $eventsDays = max(1, (int) $this->option('events-days'));
        $webhooksDays = max(1, (int) $this->option('webhooks-days'));
        $notificationsDays = max(1, (int) $this->option('notifications-days'));
        $dryRun = (bool) $this->option('dry-run');

        $this->info('====================================================');
        $this->info('AffiliateOS Data Retention & Automated Pruning Policy');
        $this->info('====================================================');

        if ($dryRun) {
            $this->warn('DRY RUN MODE: No records will be deleted.');
        }

        $results = [];

        // 1. Affiliate Clicks
        $clicksCutoff = now()->subDays($clicksDays)->toDateTimeString();
        $clicksQuery = DB::table('affiliate_clicks')->where('created_at', '<', $clicksCutoff);
        $clicksCount = $clicksQuery->count();
        if (! $dryRun && $clicksCount > 0) {
            $clicksQuery->delete();
        }
        $results[] = ['Affiliate Clicks', "Older than {$clicksDays} days", $clicksCount, $dryRun ? 'Simulated' : 'Deleted'];

        // 2. Ad Conversion Events
        $eventsCutoff = now()->subDays($eventsDays)->toDateTimeString();
        $eventsQuery = DB::table('ad_conversion_events')->where('created_at', '<', $eventsCutoff);
        $eventsCount = $eventsQuery->count();
        if (! $dryRun && $eventsCount > 0) {
            $eventsQuery->delete();
        }
        $results[] = ['Ad Conversion Events', "Older than {$eventsDays} days", $eventsCount, $dryRun ? 'Simulated' : 'Deleted'];

        // 3. Webhook Deliveries
        $webhooksCutoff = now()->subDays($webhooksDays)->toDateTimeString();
        $webhooksQuery = DB::table('webhook_deliveries')->where('created_at', '<', $webhooksCutoff);
        $webhooksCount = $webhooksQuery->count();
        if (! $dryRun && $webhooksCount > 0) {
            $webhooksQuery->delete();
        }
        $results[] = ['Webhook Deliveries', "Older than {$webhooksDays} days", $webhooksCount, $dryRun ? 'Simulated' : 'Deleted'];

        // 4. Notification Logs
        $notificationsCutoff = now()->subDays($notificationsDays)->toDateTimeString();
        $notificationsQuery = DB::table('notification_logs')->where('created_at', '<', $notificationsCutoff);
        $notificationsCount = $notificationsQuery->count();
        if (! $dryRun && $notificationsCount > 0) {
            $notificationsQuery->delete();
        }
        $results[] = ['Notification Logs', "Older than {$notificationsDays} days", $notificationsCount, $dryRun ? 'Simulated' : 'Deleted'];

        $this->table(['Data Entity', 'Retention Policy', 'Affected Records', 'Status'], $results);

        $totalPruned = $clicksCount + $eventsCount + $webhooksCount + $notificationsCount;

        if (! $dryRun && $totalPruned > 0) {
            Log::info("Compliance: Automated data pruning completed. Total {$totalPruned} expired records pruned.");
        }

        $this->info("Completed successfully. Total records processed: {$totalPruned}.");

        return self::SUCCESS;
    }
}
