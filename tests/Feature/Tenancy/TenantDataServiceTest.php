<?php

declare(strict_types=1);

namespace Tests\Feature\Tenancy;

use App\Models\Affiliate;
use App\Models\Tenant;
use App\Support\Settings\GeneralSettings;
use App\Support\Tenancy\TenantDataService;
use Tests\TestCase;

class TenantDataServiceTest extends TestCase
{
    public function test_tenant_can_be_archived(): void
    {
        $tenant = $this->ensureTenantExists('store_to_archive');
        $service = new TenantDataService;

        $service->archive($tenant);

        $this->assertEquals('archived', $tenant->fresh()->status);
    }

    public function test_tenant_data_can_be_exported_safely_deleted_and_imported(): void
    {
        $tenantId = 'exportable_store_'.uniqid();
        $tenant = $this->ensureTenantExists($tenantId);

        // Add some tenant data
        $tenant->run(function () use ($tenantId) {
            $settings = GeneralSettings::load();
            $settings->platform_name = 'Custom Store Name';
            $settings->save();

            Affiliate::create([
                'name' => 'Top Affiliate',
                'code' => 'TOPAFF_'.$tenantId,
                'commission_rate' => 25,
            ]);
        });

        $service = new TenantDataService;

        // 1. Export
        $exportedData = $service->export($tenant);

        $this->assertIsArray($exportedData);
        $this->assertEquals($tenantId, $exportedData['tenant']['id']);
        $this->assertNotEmpty($exportedData['domains']);
        $this->assertNotEmpty($exportedData['settings']);
        $this->assertNotEmpty($exportedData['affiliates']);

        // 2. Safe Delete
        $service->safeDelete($tenant);

        $this->assertNull(Tenant::find($tenantId));
        $this->assertEquals(0, Affiliate::withoutTenancy()->where('tenant_id', $tenantId)->count());

        // 3. Import
        $newTenantId = 'restored_store_'.uniqid();
        $importedTenant = $service->import($newTenantId, $exportedData);

        $this->assertNotNull($importedTenant);
        $this->assertEquals($newTenantId, $importedTenant->id);

        $importedTenant->run(function () {
            $settings = GeneralSettings::load();
            $this->assertEquals('Custom Store Name', $settings->platform_name);
            $this->assertEquals(1, Affiliate::count());
        });
    }
}
