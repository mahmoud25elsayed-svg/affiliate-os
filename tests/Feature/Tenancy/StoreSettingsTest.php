<?php

declare(strict_types=1);

namespace Tests\Feature\Tenancy;

use App\Models\Tenant;
use App\Support\Settings\GeneralSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StoreSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_settings_can_be_saved_and_are_isolated_between_tenants(): void
    {
        $tenantA = Tenant::create(['id' => 'store-a', 'name' => 'Store A', 'status' => 'active']);
        $tenantB = Tenant::create(['id' => 'store-b', 'name' => 'Store B', 'status' => 'active']);

        // 1. In context of Tenant A, customize settings
        tenancy()->initialize($tenantA);
        $settingsA = GeneralSettings::load();
        $settingsA->platform_name = 'Custom Store A';
        $settingsA->primary_color = '#ff1122';
        $settingsA->logo_url = 'https://store-a.test/logo.png';
        $settingsA->save();

        $this->assertEquals('Custom Store A', GeneralSettings::load()->platform_name);
        $this->assertEquals('#ff1122', GeneralSettings::load()->primary_color);
        $this->assertEquals('https://store-a.test/logo.png', GeneralSettings::load()->logo_url);
        tenancy()->end();

        // 2. In context of Tenant B, customize with different settings
        tenancy()->initialize($tenantB);
        $settingsB = GeneralSettings::load();
        $this->assertNotEquals('Custom Store A', $settingsB->platform_name);
        $this->assertNotEquals('#ff1122', $settingsB->primary_color);

        $settingsB->platform_name = 'Store B Luxury';
        $settingsB->primary_color = '#00aa55';
        $settingsB->save();

        $this->assertEquals('Store B Luxury', GeneralSettings::load()->platform_name);
        $this->assertEquals('#00aa55', GeneralSettings::load()->primary_color);
        tenancy()->end();

        // 3. Verify Tenant A settings remain intact
        tenancy()->initialize($tenantA);
        $verifiedSettingsA = GeneralSettings::load();
        $this->assertEquals('Custom Store A', $verifiedSettingsA->platform_name);
        $this->assertEquals('#ff1122', $verifiedSettingsA->primary_color);
        tenancy()->end();
    }
}
