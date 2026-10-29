<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Reports\Services\DailyMetricsAggregator;
use App\Models\Tenant;
use Carbon\Carbon;
use Illuminate\Console\Command;

class AggregateDailyMetricsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'reports:aggregate-daily
                            {--date= : Specific date to aggregate in YYYY-MM-DD format (defaults to yesterday)}
                            {--tenant= : Specific tenant ID to aggregate}
                            {--today : Aggregate today instead of yesterday}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Compute and cache daily store metrics rollups to avoid heavy live database queries';

    public function handle(DailyMetricsAggregator $aggregator): int
    {
        $dateInput = $this->option('date');
        if (is_string($dateInput) && ! empty($dateInput)) {
            $targetDate = Carbon::parse($dateInput)->toDateString();
        } elseif ($this->option('today')) {
            $targetDate = Carbon::today()->toDateString();
        } else {
            $targetDate = Carbon::yesterday()->toDateString();
        }

        $tenantId = $this->option('tenant');

        $this->info("Starting daily metrics aggregation for date: {$targetDate}...");

        if (is_string($tenantId) && ! empty($tenantId)) {
            $tenant = Tenant::find($tenantId);
            if (! $tenant) {
                $this->error("Tenant [{$tenantId}] not found.");

                return self::FAILURE;
            }

            $metric = $aggregator->aggregateForTenant($tenant, $targetDate);
            $this->info("Successfully aggregated metrics for tenant [{$tenant->id}]: {$metric->orders_count} orders, {$metric->delivered_orders_count} delivered.");

            return self::SUCCESS;
        }

        $count = $aggregator->aggregateAllTenants($targetDate);
        $this->info("Completed daily metrics aggregation for {$count} tenants on {$targetDate}.");

        return self::SUCCESS;
    }
}
