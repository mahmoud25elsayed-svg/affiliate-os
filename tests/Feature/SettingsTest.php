<?php

declare(strict_types=1);

use App\Support\Settings\GeneralSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('GeneralSettings loads typed default values', function () {
    $settings = GeneralSettings::load();

    expect($settings->platform_name)->toBe('AffiliateOS')
        ->and($settings->default_currency)->toBe('EGP')
        ->and($settings->default_locale)->toBe('ar');
});

test('GeneralSettings persists modifications and updates cache', function () {
    $settings = GeneralSettings::load();
    $settings->platform_name = 'Custom Affiliate Platform';
    $settings->save();

    // Reload
    $reloaded = GeneralSettings::load();
    expect($reloaded->platform_name)->toBe('Custom Affiliate Platform');
});
