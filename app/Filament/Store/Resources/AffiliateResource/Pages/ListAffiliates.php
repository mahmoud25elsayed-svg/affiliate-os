<?php

declare(strict_types=1);

namespace App\Filament\Store\Resources\AffiliateResource\Pages;

use App\Filament\Store\Resources\AffiliateResource;
use Filament\Resources\Pages\ListRecords;

class ListAffiliates extends ListRecords
{
    protected static string $resource = AffiliateResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
