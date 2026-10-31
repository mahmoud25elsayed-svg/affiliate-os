<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Support\Location\Governorate;
use Illuminate\Database\Seeder;

/**
 * Regions of every supported non-Egyptian country (database/data/regions.php).
 * Production does not need this: Governorate::forCountries() seeds a country on first use.
 */
class WorldRegionsSeeder extends Seeder
{
    public function run(): void
    {
        foreach (array_keys((array) config('countries')) as $country) {
            Governorate::ensureSeeded((string) $country);
        }
    }
}
