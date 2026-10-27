<?php

declare(strict_types=1);

namespace App\Filament\Store\Resources\ProductResource\Pages;

use App\Domain\Catalog\Services\ProductService;
use App\Filament\Store\Resources\ProductResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateProduct extends CreateRecord
{
    protected static string $resource = ProductResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        /** @var ProductService $service */
        $service = app(ProductService::class);

        return $service->createProduct($data);
    }
}
