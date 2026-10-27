<?php

declare(strict_types=1);

use App\Support\Location\City;
use App\Support\Location\Governorate;
use Database\Seeders\EgyptGovernoratesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('EgyptGovernoratesSeeder populates all 27 governorates and their cities', function () {
    $this->seed(EgyptGovernoratesSeeder::class);

    expect(Governorate::count())->toBe(27);

    // Verify Cairo
    $cairo = Governorate::where('code', 'CAI')->first();
    expect($cairo)->not->toBeNull()
        ->and($cairo->name_ar)->toBe('القاهرة')
        ->and($cairo->name_en)->toBe('Cairo')
        ->and($cairo->cities()->count())->toBeGreaterThan(0);

    // Verify Alexandria
    $alex = Governorate::where('code', 'ALX')->first();
    expect($alex)->not->toBeNull()
        ->and($alex->name_ar)->toBe('الإسكندرية')
        ->and($alex->name_en)->toBe('Alexandria')
        ->and($alex->cities()->count())->toBeGreaterThan(0);

    // Total cities seeded
    expect(City::count())->toBeGreaterThan(50);
});
