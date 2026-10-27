<?php

declare(strict_types=1);

namespace Tests\Feature\Tenancy;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ResourceIsolationTest extends TestCase
{
    public function test_cache_is_isolated_between_tenants(): void
    {
        $tenantA = $this->ensureTenantExists('cache_tenant_a');
        $tenantB = $this->ensureTenantExists('cache_tenant_b');

        $tenantA->run(function () {
            Cache::put('secret_key', 'value_for_a', 60);
        });

        $tenantB->run(function () {
            Cache::put('secret_key', 'value_for_b', 60);
        });

        $tenantA->run(function () {
            $this->assertEquals('value_for_a', Cache::get('secret_key'));
        });

        $tenantB->run(function () {
            $this->assertEquals('value_for_b', Cache::get('secret_key'));
        });
    }

    public function test_filesystem_storage_is_isolated_between_tenants(): void
    {
        $tenantA = $this->ensureTenantExists('files_tenant_a');
        $tenantB = $this->ensureTenantExists('files_tenant_b');

        $tenantA->run(function () {
            Storage::disk('local')->put('isolation_test.txt', 'CONTENT_A');
            $this->assertEquals('CONTENT_A', Storage::disk('local')->get('isolation_test.txt'));
        });

        $tenantB->run(function () {
            Storage::disk('local')->put('isolation_test.txt', 'CONTENT_B');
            $this->assertEquals('CONTENT_B', Storage::disk('local')->get('isolation_test.txt'));
        });

        $tenantA->run(function () {
            $this->assertEquals('CONTENT_A', Storage::disk('local')->get('isolation_test.txt'));
        });
    }

    public function test_logger_bootstrapper_shares_tenant_context(): void
    {
        $tenant = $this->ensureTenantExists('logging_tenant');

        $tenant->run(function () use ($tenant) {
            $context = Log::sharedContext();
            $this->assertArrayHasKey('tenant_id', $context);
            $this->assertEquals($tenant->id, $context['tenant_id']);
        });

        $contextOutside = Log::sharedContext();
        $this->assertTrue(! isset($contextOutside['tenant_id']) || $contextOutside['tenant_id'] === null);
    }
}
