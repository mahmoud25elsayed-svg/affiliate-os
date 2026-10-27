<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Lunar\Models\Language;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        Language::firstOrCreate(['code' => 'ar'], ['name' => 'Arabic', 'default' => true]);
        Language::firstOrCreate(['code' => 'en'], ['name' => 'English', 'default' => false]);

        $this->call(EgyptGovernoratesSeeder::class);
    }
}
