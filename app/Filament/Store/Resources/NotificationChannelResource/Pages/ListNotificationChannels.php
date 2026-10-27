<?php

declare(strict_types=1);

namespace App\Filament\Store\Resources\NotificationChannelResource\Pages;

use App\Filament\Store\Resources\NotificationChannelResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListNotificationChannels extends ListRecords
{
    protected static string $resource = NotificationChannelResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
