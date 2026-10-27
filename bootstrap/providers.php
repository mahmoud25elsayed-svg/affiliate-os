<?php

use App\Providers\AppServiceProvider;
use App\Providers\Filament\PlatformPanelProvider;
use App\Providers\Filament\StorePanelProvider;
use App\Providers\HorizonServiceProvider;
use App\Providers\LunarTenancyServiceProvider;
use App\Providers\TenancyServiceProvider;

return [
    AppServiceProvider::class,
    PlatformPanelProvider::class,
    StorePanelProvider::class,
    HorizonServiceProvider::class,
    LunarTenancyServiceProvider::class,
    TenancyServiceProvider::class,
];
