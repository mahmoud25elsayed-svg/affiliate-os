<?php

declare(strict_types=1);

namespace App\Filament\Store\Resources\WarehouseResource\Pages;

use App\Filament\Store\Resources\WarehouseResource;
use Filament\Resources\Pages\ListRecords;

class ListWarehouses extends ListRecords
{
    protected static string $resource = WarehouseResource::class;
}
