<?php

declare(strict_types=1);

namespace App\Filament\Store\Resources\FlashSaleResource\Pages;

use App\Filament\Store\Resources\FlashSaleResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditFlashSale extends EditRecord
{
    protected static string $resource = FlashSaleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
