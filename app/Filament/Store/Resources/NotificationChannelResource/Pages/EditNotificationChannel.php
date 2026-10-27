<?php

declare(strict_types=1);

namespace App\Filament\Store\Resources\NotificationChannelResource\Pages;

use App\Filament\Store\Resources\NotificationChannelResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditNotificationChannel extends EditRecord
{
    protected static string $resource = NotificationChannelResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
