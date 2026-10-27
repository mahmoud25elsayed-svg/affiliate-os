<?php

declare(strict_types=1);

namespace App\Filament\Store\Resources\ProductBundleResource\Pages;

use App\Filament\Store\Resources\ProductBundleResource;
use Filament\Resources\Pages\CreateRecord;

class CreateProductBundle extends CreateRecord
{
    protected static string $resource = ProductBundleResource::class;
}
