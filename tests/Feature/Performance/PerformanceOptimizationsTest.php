<?php

declare(strict_types=1);

namespace Tests\Feature\Performance;

use App\Models\Affiliate;
use App\Models\Domain;
use App\Models\Tenant;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PerformanceOptimizationsTest extends TestCase
{
    public function test_composite_performance_indexes_exist_on_high_throughput_tables(): void
    {
        $expectedIndexes = [
            'lunar_orders' => ['idx_orders_tenant_status_created', 'idx_orders_tenant_created'],
            'shipments' => ['idx_shipments_tenant_status_created', 'idx_shipments_tenant_tracking', 'idx_shipments_tenant_courier'],
            'payout_requests' => ['idx_payouts_tenant_status_created', 'idx_payouts_tenant_affiliate_status'],
            'tickets' => ['idx_tickets_tenant_level_status', 'idx_tickets_tenant_sla_due'],
            'call_attempts' => ['idx_call_attempts_tenant_order_created', 'idx_call_attempts_tenant_user_created'],
            'tracking_links' => ['idx_tracking_links_tenant_slug', 'idx_tracking_links_tenant_affiliate'],
            'ad_conversion_events' => ['idx_ad_events_tenant_status_created'],
            'webhook_deliveries' => ['idx_wh_deliveries_tenant_status_created'],
            'inventory_balances' => ['idx_inv_balances_tenant_variant'],
            'affiliates' => ['idx_affiliates_tenant_status_created', 'idx_affiliates_tenant_phone'],
        ];

        foreach ($expectedIndexes as $table => $indexes) {
            $this->assertTrue(Schema::hasTable($table), "Table [{$table}] must exist.");

            $sqliteIndexes = DB::select("PRAGMA index_list('{$table}')");
            $indexNames = array_map(fn ($idx) => (string) $idx->name, $sqliteIndexes);

            foreach ($indexes as $indexName) {
                $this->assertContains(
                    $indexName,
                    $indexNames,
                    "Expected composite index [{$indexName}] on table [{$table}] was not found."
                );
            }
        }
    }

    public function test_queue_cron_runs_with_domain_priorities(): void
    {
        $exitCode = Artisan::call('queue:cron', [
            '--stop-when-empty' => true,
            '--max-time' => 5,
        ]);

        $this->assertEquals(0, $exitCode, 'queue:cron command must execute successfully with exit code 0.');
        $output = Artisan::output();
        $this->assertStringContainsString('finance,notifications,courier,default', $output);
    }

    public function test_storefront_http_cache_middleware_emits_etags_and_handles_304(): void
    {
        $tenant = $this->ensureTenantExists('tenant_cache_perf_'.uniqid());
        $domain = "perf-cache-{$tenant->id}.localhost";
        $tenant->domains()->create(['domain' => $domain]);

        tenancy()->initialize($tenant);

        // 1. Initial request to public help center
        $response1 = $this->get("http://{$domain}/help");
        $response1->assertOk();
        $this->assertStringContainsString('public', (string) $response1->headers->get('Cache-Control'));
        $this->assertStringContainsString('max-age=60', (string) $response1->headers->get('Cache-Control'));
        $this->assertStringContainsString('stale-while-revalidate=30', (string) $response1->headers->get('Cache-Control'));

        $etag = $response1->headers->get('ETag');
        $this->assertNotEmpty($etag, 'Expected ETag header on public storefront cached response.');

        // 2. Subsequent request with matching If-None-Match header returns 304 Not Modified
        $response2 = $this->withHeaders([
            'If-None-Match' => $etag,
        ])->get("http://{$domain}/help");

        $this->assertEquals(304, $response2->status(), 'Matching ETag must return 304 Not Modified without regenerating content.');

        // 3. Authenticated requests must bypass public caching
        /** @var Affiliate $affiliate */
        $affiliate = Affiliate::create([
            'tenant_id' => (string) $tenant->id,
            'name' => 'Cached Aff',
            'email' => 'cached_aff_'.uniqid().'@example.com',
            'phone' => '010'.rand(10000000, 99999999),
            'code' => 'AFF_C_'.rand(1000, 9999),
            'password' => 'Password123!',
        ]);

        $authResponse = $this->actingAs($affiliate, 'affiliate')
            ->get("http://{$domain}/help");

        $authResponse->assertOk();
        $this->assertStringContainsString('no-cache', (string) $authResponse->headers->get('Cache-Control'));

        tenancy()->end();
    }

    public function test_perf_seed_tenants_and_clean_command(): void
    {
        $seedExit = Artisan::call('perf:seed-tenants', [
            '--count' => 3,
            '--orders-per-tenant' => 2,
        ]);

        $this->assertEquals(0, $seedExit);
        $this->assertTrue(Tenant::where('id', 'perf_tenant_1')->exists());
        $this->assertTrue(Domain::where('domain', 'perf-tenant-1.affiliate-os.test')->exists());

        $cleanExit = Artisan::call('perf:seed-tenants', [
            '--clean' => true,
        ]);

        $this->assertEquals(0, $cleanExit);
        $this->assertFalse(Tenant::where('id', 'perf_tenant_1')->exists());
        $this->assertFalse(Domain::where('domain', 'perf-tenant-1.affiliate-os.test')->exists());
    }
}
