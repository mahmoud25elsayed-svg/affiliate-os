<?php

declare(strict_types=1);

namespace Tests\Feature\Tenancy;

use App\Jobs\ProvisionTenant;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Settings\GeneralSettings;
use Lunar\Models\Channel;
use Lunar\Models\Currency;
use Lunar\Models\CustomerGroup;
use Lunar\Models\Language;
use Tests\TestCase;

class ProvisionTenantTest extends TestCase
{
    public function test_provision_tenant_executes_in_under_10_seconds_and_seeds_correctly(): void
    {
        $tenantId = 'quick_merchant_'.uniqid();
        $domain = "{$tenantId}.platform.test";

        $startTime = microtime(true);

        $job = new ProvisionTenant(
            tenantId: $tenantId,
            name: 'Quick Merchant Store',
            domain: $domain,
            ownerData: [
                'name' => 'Store Owner',
                'email' => "owner_{$tenantId}@test.com",
                'password' => 'secret123',
            ]
        );

        $tenant = $job->handle();
        $durationSeconds = microtime(true) - $startTime;

        // 1. Acceptance benchmark: Provisioning must be under 10 seconds
        $this->assertLessThan(10.0, $durationSeconds, "Provisioning took {$durationSeconds}s, exceeding 10s limit.");

        // 2. Tenant and domain assertions
        $this->assertEquals($tenantId, $tenant->id);
        $this->assertEquals('Quick Merchant Store', $tenant->name);
        $this->assertTrue($tenant->domains()->where('domain', $domain)->exists());

        // 3. Verification within tenant context
        $tenant->run(function () use ($tenant, $tenantId) {
            // Currency
            $egp = Currency::getDefault();
            $this->assertNotNull($egp);
            $this->assertEquals('EGP', $egp->code);
            $this->assertEquals(1.0, (float) $egp->exchange_rate);

            // Languages
            $this->assertTrue(Language::where('code', 'ar')->exists());
            $this->assertTrue(Language::where('code', 'en')->exists());

            // Default Channel
            $channel = Channel::first();
            $this->assertNotNull($channel);
            $this->assertEquals('default', $channel->handle);

            // Default Customer Group
            $group = CustomerGroup::first();
            $this->assertNotNull($group);
            $this->assertEquals('default', $group->handle);

            // General Settings
            $settings = GeneralSettings::load();
            $this->assertEquals('Quick Merchant Store', $settings->platform_name);

            // Owner User
            $owner = User::where('email', "owner_{$tenantId}@test.com")->first();
            $this->assertNotNull($owner);
            $this->assertEquals($tenant->id, $owner->tenant_id);
        });
    }

    public function test_provision_tenant_is_idempotent_and_reexecutable(): void
    {
        $tenantId = 'idempotent_store_'.uniqid();
        $domain = "{$tenantId}.platform.test";

        $job = new ProvisionTenant(
            tenantId: $tenantId,
            name: 'Idempotent Store',
            domain: $domain,
            ownerData: [
                'name' => 'Owner',
                'email' => "idemp_{$tenantId}@test.com",
                'password' => 'secret123',
            ]
        );

        // Run first time
        $tenant1 = $job->handle();

        // Run second time (re-execution must not duplicate or throw unique violations)
        $tenant2 = $job->handle();

        $this->assertEquals($tenant1->id, $tenant2->id);
        $this->assertEquals(1, $tenant2->domains()->where('domain', $domain)->count());

        $tenant2->run(function () use ($tenantId) {
            $this->assertEquals(1, Currency::where('code', 'EGP')->count());
            $this->assertEquals(1, Channel::where('handle', 'default')->count());
            $this->assertEquals(1, User::where('email', "idemp_{$tenantId}@test.com")->count());
        });
    }
}
