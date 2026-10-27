<?php

declare(strict_types=1);

namespace App\Filament\Store\Resources\InventoryBalanceResource\Pages;

use App\Filament\Store\Resources\InventoryBalanceResource;
use Filament\Resources\Pages\ListRecords;

class ListInventoryBalances extends ListRecords
{
    protected static string $resource = InventoryBalanceResource::class;
}
