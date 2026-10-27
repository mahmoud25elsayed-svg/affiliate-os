<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Contracts;

use App\Domain\Notifications\DTOs\NotificationMessage;
use App\Domain\Notifications\DTOs\NotificationResult;

interface NotificationDriverInterface
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function send(NotificationMessage $message, array $config = []): NotificationResult;

    public function getProviderName(): string;
}
