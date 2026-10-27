<?php

declare(strict_types=1);

namespace App\Filament\Store\Resources\DailyDealResource\Pages;

use App\Filament\Store\Resources\DailyDealResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditDailyDeal extends EditRecord
{
    protected static string $resource = DailyDealResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
