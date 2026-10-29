<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Lunar\Models\Channel;
use Lunar\Models\Currency;
use Lunar\Models\CustomerGroup;
use Lunar\Models\Language;
use Lunar\Models\ProductType;
use Lunar\Models\TaxClass;

class TenantSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Channel
        if (! Channel::whereDefault(true)->exists()) {
            Channel::create([
                'name' => 'Webstore',
                'handle' => 'webstore',
                'default' => true,
                'url' => 'http://localhost',
            ]);
        }

        // 2. Language
        if (! Language::whereDefault(true)->exists()) {
            Language::create([
                'code' => 'ar',
                'name' => 'Arabic',
                'default' => true,
            ]);
        }

        // 3. Currency
        if (! Currency::whereDefault(true)->exists()) {
            Currency::create([
                'code' => 'EGP',
                'name' => 'Egyptian Pound',
                'exchange_rate' => 1,
                'decimal_places' => 2,
                'default' => true,
                'enabled' => true,
            ]);
        }

        // 4. Customer Group
        if (! CustomerGroup::whereDefault(true)->exists()) {
            CustomerGroup::create([
                'name' => 'Retail',
                'handle' => 'retail',
                'default' => true,
            ]);
        }

        // 5. Tax Class
        if (! TaxClass::whereDefault(true)->exists()) {
            TaxClass::create([
                'name' => 'Default Tax Class',
                'default' => true,
            ]);
        }

        // 6. Product Type
        if (! ProductType::exists()) {
            ProductType::create([
                'name' => 'General',
            ]);
        }

        // No default users here: a shared "admin@tenant.local / password" account would be a backdoor into
        // every store this seeder runs for. Owners are created by ProvisionTenant / merchant onboarding.
    }
}
