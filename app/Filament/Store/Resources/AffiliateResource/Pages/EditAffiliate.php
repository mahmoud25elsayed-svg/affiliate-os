<?php

declare(strict_types=1);

namespace App\Filament\Store\Resources\AffiliateResource\Pages;

use App\Filament\Store\Resources\AffiliateResource;
use Filament\Resources\Pages\EditRecord;

class EditAffiliate extends EditRecord
{
    protected static string $resource = AffiliateResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
