<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Onboarding\Services\TenantDemoDataService;
use App\Models\Tenant;
use Illuminate\Console\Command;

class TenantSeedDemoDataCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tenant:seed-demo {tenant : The ID of the tenant}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Seed demo catalog products, affiliates, and sample orders for a tenant onboarding trial';

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

        $this->info("Seeding demo data for tenant [{$tenantId}]...");

        $results = $demoService->seedDemoData($tenant);

        $this->info(sprintf(
            'Demo data seeded successfully: %d products, %d affiliates, %d orders, %d customers.',
            $results['products'],
            $results['affiliates'],
            $results['orders'],
            $results['customers']
        ));

        return self::SUCCESS;
    }
}
