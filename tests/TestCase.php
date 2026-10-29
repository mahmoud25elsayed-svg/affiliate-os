<?php

namespace Tests;

use App\Jobs\ProvisionTenant;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Lunar\Models\Language;

abstract class TestCase extends BaseTestCase
{
    // Every test runs against a fresh in-memory schema (see phpunit.xml), never the dev database.
    use RefreshDatabase;

    protected function ensureTenantExists(string $id, ?string $domain = null): Tenant
    {
        $domain = $domain ?? "{$id}.localhost";

        if (Language::count() === 0) {
            Language::firstOrCreate(['code' => 'ar'], ['name' => 'Arabic', 'default' => true]);
            Language::firstOrCreate(['code' => 'en'], ['name' => 'English', 'default' => false]);
        }

        /** @var Tenant|null $tenant */
        $tenant = Tenant::find($id);
        if ($tenant) {
            (new ProvisionTenant(
                tenantId: $id,
                name: ucfirst($id),
                domain: $domain,
            ))->handle();

            return $tenant;
        }

        return (new ProvisionTenant(
            tenantId: $id,
            name: ucfirst($id),
            domain: $domain,
            ownerData: [
                'name' => 'Owner '.ucfirst($id),
                'email' => "owner@{$id}.test",
                'password' => 'secret123',
            ]
        ))->handle();
    }
}
