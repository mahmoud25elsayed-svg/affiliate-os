<?php

declare(strict_types=1);

namespace App\Filament\Store\Resources\NotificationChannelResource\Pages;

use App\Filament\Store\Resources\NotificationChannelResource;
use Filament\Resources\Pages\CreateRecord;

class CreateNotificationChannel extends CreateRecord
{
    protected static string $resource = NotificationChannelResource::class;
}
