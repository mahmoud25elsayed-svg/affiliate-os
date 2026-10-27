<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use Illuminate\Console\Command;

class CreateTenantCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tenant:create {id} {domain?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a tenant with dedicated database, run migrations, seeds, and measure provisioning time.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $id = $this->argument('id');
        $domain = $this->argument('domain') ?? "{$id}.localhost";

        $this->info("Starting provisioning for tenant [{$id}] with domain [{$domain}]...");

        $startTime = microtime(true);

        // 1. Create tenant (fires TenantCreated -> CreateDatabase -> MigrateDatabase -> SeedDatabase)
        $tenant = Tenant::create([
            'id' => $id,
        ]);

        // 2. Attach domain
        $tenant->domains()->create([
            'domain' => $domain,
        ]);

        $durationMs = round((microtime(true) - $startTime) * 1000, 2);
        $durationSec = round($durationMs / 1000, 3);

        $this->info("✔️ Tenant [{$id}] successfully provisioned in {$durationMs} ms ({$durationSec} s).");

        return 0;
    }
}
