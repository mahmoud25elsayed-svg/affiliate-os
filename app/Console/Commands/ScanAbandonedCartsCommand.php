<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Promotions\Services\AbandonedCartService;
use App\Models\Tenant;
use Illuminate\Console\Command;

class ScanAbandonedCartsCommand extends Command
{
    protected $signature = 'promotions:scan-abandoned-carts {--minutes=30 : Idle minutes threshold to consider cart abandoned}';

    protected $aliases = ['cart:scan-abandoned'];

    protected $description = 'Scan idle carts and trigger abandonment reminder events';

    public function handle(AbandonedCartService $abandonedCartService): int
    {
        $minutes = (int) $this->option('minutes');
        $this->info("Scanning for carts abandoned for more than {$minutes} minutes...");

        if (function_exists('tenancy') && tenancy()->initialized) {
            $detected = $abandonedCartService->scanAndNotifyAbandoned($minutes);
            $this->info("Successfully detected and flagged {$detected} abandoned cart(s).");

            return self::SUCCESS;
        }

        $total = 0;
        /** @var Tenant $tenant */
        foreach (Tenant::all() as $tenant) {
            $tenant->run(function () use ($abandonedCartService, $minutes, &$total): void {
                $total += $abandonedCartService->scanAndNotifyAbandoned($minutes);
            });
        }

        $this->info("Successfully detected and flagged {$total} abandoned cart(s) across all tenants.");

        return self::SUCCESS;
    }
}
