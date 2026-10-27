<?php

declare(strict_types=1);

namespace App\Filament\Store\Resources\DailyDealResource\Pages;

use App\Filament\Store\Resources\DailyDealResource;
use Filament\Resources\Pages\CreateRecord;

class CreateDailyDeal extends CreateRecord
{
    protected static string $resource = DailyDealResource::class;
}
