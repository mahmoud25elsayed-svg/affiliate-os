<?php

declare(strict_types=1);

namespace App\Filament\Store\Resources\ProductBundleResource\Pages;

use App\Filament\Store\Resources\ProductBundleResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListProductBundles extends ListRecords
{
    protected static string $resource = ProductBundleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label(__('إضافة باقة جديدة')),
        ];
    }
}
