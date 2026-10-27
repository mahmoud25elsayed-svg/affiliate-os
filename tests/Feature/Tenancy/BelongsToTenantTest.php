<?php

declare(strict_types=1);

namespace Tests\Feature\Tenancy;

use App\Models\Affiliate;
use App\Models\Tenant;
use App\Support\Tenancy\Exceptions\TenantCannotBeNullException;
use Tests\TestCase;

class BelongsToTenantTest extends TestCase
{
    public function test_saving_model_without_tenant_throws_exception(): void
    {
        $this->expectException(TenantCannotBeNullException::class);

        // Attempt to create affiliate outside any tenant context
        Affiliate::create([
            'name' => 'Orphan Affiliate',
            'code' => 'ORPHAN1',
            'commission_rate' => 10,
        ]);
    }

    public function test_tenant_id_is_automatically_assigned_when_tenancy_is_initialized(): void
    {
        $tenant = $this->ensureTenantExists('store_alpha');

        $tenant->run(function () use ($tenant) {
            $affiliate = Affiliate::create([
                'name' => 'Alpha Affiliate',
                'code' => 'ALPHA10',
                'commission_rate' => 12,
            ]);

            $this->assertEquals($tenant->id, $affiliate->tenant_id);
            $this->assertEquals($tenant->id, $affiliate->tenant->id);
        });
    }

    public function test_global_scope_filters_by_active_tenant(): void
    {
        $tenant1 = $this->ensureTenantExists('store_one');
        $tenant2 = $this->ensureTenantExists('store_two');

        $tenant1->run(function () {
            Affiliate::where('code', 'CODE1')->delete();
            Affiliate::create([
                'name' => 'Affiliate Store 1',
                'code' => 'CODE1',
                'commission_rate' => 10,
            ]);
        });

        $tenant2->run(function () {
            Affiliate::where('code', 'CODE2')->delete();
            Affiliate::create([
                'name' => 'Affiliate Store 2',
                'code' => 'CODE2',
                'commission_rate' => 15,
            ]);
        });

        // Tenant 1 can only see CODE1
        $tenant1->run(function () {
            $this->assertEquals(1, Affiliate::where('code', 'CODE1')->count());
            $this->assertEquals(0, Affiliate::where('code', 'CODE2')->count());
        });

        // Tenant 2 can only see CODE2
        $tenant2->run(function () {
            $this->assertEquals(0, Affiliate::where('code', 'CODE1')->count());
            $this->assertEquals(1, Affiliate::where('code', 'CODE2')->count());
        });

        // withoutTenancy macro accesses all
        $tenant1->run(function () {
            $this->assertTrue(Affiliate::withoutTenancy()->where('code', 'CODE2')->exists());
        });
    }
}
