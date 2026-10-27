<?php

declare(strict_types=1);

namespace App\Filament\Store\Resources\ProductBundleResource\Pages;

use App\Filament\Store\Resources\ProductBundleResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditProductBundle extends EditRecord
{
    protected static string $resource = ProductBundleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
