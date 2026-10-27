<?php

declare(strict_types=1);

namespace App\Filament\Platform\Resources\SubscriptionInvoiceResource\Pages;

use App\Filament\Platform\Resources\SubscriptionInvoiceResource;
use Filament\Resources\Pages\ListRecords;

class ListSubscriptionInvoices extends ListRecords
{
    protected static string $resource = SubscriptionInvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
