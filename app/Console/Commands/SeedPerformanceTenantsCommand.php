<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Domain;
use App\Models\Tenant;
use App\Support\Tenancy\Scopes\TenantScope;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Lunar\Base\ValueObjects\Cart\TaxBreakdown;
use Lunar\Models\Channel;
use Lunar\Models\Currency;
use Lunar\Models\Order;

class SeedPerformanceTenantsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'perf:seed-tenants
                            {--count=200 : Number of test tenants to seed for load testing}
                            {--orders-per-tenant=3 : Seed minimal orders per tenant for realistic table sizes}
                            {--clean : Clean up all seeded performance tenants}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Seed lightweight test tenants on staging to verify query performance and indexes under 200+ tenants';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if ($this->option('clean')) {
            return $this->cleanTenants();
        }

        $count = (int) $this->option('count');
        $ordersPerTenant = (int) $this->option('orders-per-tenant');

        $this->info("Seeding {$count} performance test tenants for staging load testing...");

        $channel = Channel::first() ?? Channel::create(['name' => 'Default', 'handle' => 'default', 'default' => true]);
        $currency = Currency::first() ?? Currency::create(['code' => 'EGP', 'name' => 'Egyptian Pound', 'exchange_rate' => 1, 'decimal_places' => 2, 'enabled' => true, 'default' => true]);

        $bar = $this->output->createProgressBar($count);
        $bar->start();

        for ($i = 1; $i <= $count; $i++) {
            $tenantId = "perf_tenant_{$i}";

            /** @var Tenant $tenant */
            $tenant = Tenant::firstOrCreate(
                ['id' => $tenantId],
                [
                    'name' => "متجر الأداء التجريبي {$i}",
                    'plan_id' => 'growth',
                    'status' => 'active',
                ]
            );

            // Create subdomain
            Domain::firstOrCreate(
                ['domain' => "perf-tenant-{$i}.affiliate-os.test"],
                [
                    'tenant_id' => $tenant->id,
                    'is_primary' => true,
                    'is_custom' => false,
                ]
            );

            // Seed minimal orders for composite index benchmarks
            for ($o = 1; $o <= $ordersPerTenant; $o++) {
                $ref = "ORD-PERF-{$i}-{$o}";
                if (! Order::withoutGlobalScope(TenantScope::class)->where('reference', $ref)->exists()) {
                    Order::withoutGlobalScope(TenantScope::class)->create([
                        'tenant_id' => (string) $tenant->id,
                        'channel_id' => $channel->id,
                        'currency_code' => $currency->code,
                        'status' => $o % 2 === 0 ? 'delivered' : 'pending_confirmation',
                        'reference' => $ref,
                        'customer_reference' => "CUST-PERF-{$i}-{$o}",
                        'sub_total' => 15000 * $o,
                        'discount_total' => 0,
                        'shipping_total' => 4500,
                        'tax_total' => 0,
                        'tax_breakdown' => new TaxBreakdown,
                        'total' => (15000 * $o) + 4500,
                    ]);
                }
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("Successfully seeded {$count} performance tenants with orders and composite indexes.");

        return self::SUCCESS;
    }

    protected function cleanTenants(): int
    {
        $this->warn('Cleaning up all performance test tenants...');

        $tenantIds = Tenant::where('id', 'like', 'perf_tenant_%')->pluck('id')->all();
        $count = count($tenantIds);

        if ($count === 0) {
            $this->info('No performance tenants found.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($tenantIds) {
            Order::withoutGlobalScope(TenantScope::class)->whereIn('tenant_id', $tenantIds)->delete();
            Domain::whereIn('tenant_id', $tenantIds)->delete();
            Tenant::whereIn('id', $tenantIds)->delete();
        });

        $this->info("Removed {$count} performance tenants and their associated test data.");

        return self::SUCCESS;
    }
}
