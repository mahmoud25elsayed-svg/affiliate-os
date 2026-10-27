<?php

declare(strict_types=1);

namespace App\Filament\Store\Resources\AbandonedCartResource\Pages;

use App\Filament\Store\Resources\AbandonedCartResource;
use Filament\Resources\Pages\ListRecords;

class ListAbandonedCarts extends ListRecords
{
    protected static string $resource = AbandonedCartResource::class;
}
