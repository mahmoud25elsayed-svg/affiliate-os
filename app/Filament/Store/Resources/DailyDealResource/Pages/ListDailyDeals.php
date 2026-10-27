<?php

declare(strict_types=1);

namespace App\Filament\Store\Resources\DailyDealResource\Pages;

use App\Filament\Store\Resources\DailyDealResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListDailyDeals extends ListRecords
{
    protected static string $resource = DailyDealResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('إضافة صفقة يومية جديدة'),
        ];
    }
}
