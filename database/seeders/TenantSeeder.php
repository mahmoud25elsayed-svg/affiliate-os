<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Lunar\Admin\Models\Staff;
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

        // 7. Store Owner User
        User::firstOrCreate(
            ['email' => 'admin@tenant.local'],
            [
                'name' => 'Store Owner',
                'password' => bcrypt('password'),
            ]
        );

        // 8. Staff for Lunar Panel
        Staff::firstOrCreate(
            ['email' => 'admin@tenant.local'],
            [
                'firstname' => 'Store',
                'lastname' => 'Admin',
                'password' => bcrypt('password'),
                'admin' => true,
            ]
        );
    }
}
