<?php

declare(strict_types=1);

namespace App\Filament\Store\Resources\FlashSaleResource\Pages;

use App\Filament\Store\Resources\FlashSaleResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListFlashSales extends ListRecords
{
    protected static string $resource = FlashSaleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label(__('إضافة عرض فلاش سيل جديد')),
        ];
    }
}
