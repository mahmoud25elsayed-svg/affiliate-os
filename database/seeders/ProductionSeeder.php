<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Plan;
use App\Models\User;
use App\Support\Location\Governorate;
use Illuminate\Database\Seeder;
use Lunar\Models\Language;

class ProductionSeeder extends Seeder
{
    /**
     * Run production base seeds (Governorates, Plans, Languages).
     * Strictly idempotent: safe to run multiple times during deployments.
     */
    public function run(): void
    {
        $this->command?->info('Seeding Production Base Data...');

        // 1. Languages
        Language::firstOrCreate(
            ['code' => 'ar'],
            ['name' => 'Arabic', 'default' => true]
        );

        Language::firstOrCreate(
            ['code' => 'en'],
            ['name' => 'English', 'default' => false]
        );
        $this->command?->info('✔️ System languages seeded (Arabic default, English).');

        // 2. Egypt Governorates
        $this->call(EgyptGovernoratesSeeder::class);
        $govCount = Governorate::count();
        $this->command?->info("✔️ Egypt Governorates verified ({$govCount} governorates).");

        // 3. Subscription Plans & Limits
        $this->call(PlanSeeder::class);
        $planCount = Plan::count();
        $this->command?->info("✔️ Subscription Plans verified ({$planCount} active plans).");

        // 4. Admin Check
        $adminCount = User::where('is_platform_admin', true)->count();
        if ($adminCount === 0) {
            $this->command?->warn('⚠️ No Platform Super Admin detected. Run [php artisan platform:create-super-admin {email}] to provision.');
        } else {
            $this->command?->info("✔️ Platform Administrators verified ({$adminCount} admin accounts).");
        }

        $this->command?->info('Production base seeds completed successfully.');
    }
}
