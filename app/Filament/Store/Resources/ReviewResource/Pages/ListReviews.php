<?php

declare(strict_types=1);

namespace App\Filament\Store\Resources\ReviewResource\Pages;

use App\Filament\Store\Resources\ReviewResource;
use Filament\Resources\Pages\ListRecords;

class ListReviews extends ListRecords
{
    protected static string $resource = ReviewResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
