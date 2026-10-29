<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Onboarding\Services\TenantDemoDataService;
use App\Models\Tenant;
use Illuminate\Console\Command;

class TenantPurgeDemoDataCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tenant:purge-demo {tenant : The ID of the tenant}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Purge all demo data for a tenant without affecting real store records';

    /**
     * Execute the console command.
     */
    public function handle(TenantDemoDataService $demoService): int
    {
        /** @var string $tenantId */
        $tenantId = $this->argument('tenant');

        /** @var Tenant|null $tenant */
        $tenant = Tenant::find($tenantId);

        if (! $tenant) {
            $this->error("Tenant [{$tenantId}] not found.");

            return self::FAILURE;
        }

        $this->info("Purging demo data for tenant [{$tenantId}]...");

        $results = $demoService->purgeDemoData($tenant);

        $this->info(sprintf(
            'Demo data purged: %d products, %d affiliates, %d orders, %d customers.',
            $results['purged_products'],
            $results['purged_affiliates'],
            $results['purged_orders'],
            $results['purged_customers']
        ));

        return self::SUCCESS;
    }
}
