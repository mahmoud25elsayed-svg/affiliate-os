<?php

declare(strict_types=1);

namespace App\Domain\Fulfillment\Services;

use App\Domain\Fulfillment\Contracts\CourierDriverInterface;
use App\Domain\Fulfillment\Drivers\BostaCourierDriver;
use App\Domain\Fulfillment\Drivers\MockCourierDriver;
use App\Models\CourierAccount;

class CourierManager
{
    /**
     * Resolve the courier driver implementation for a given courier account.
     */
    public function driver(CourierAccount $account): CourierDriverInterface
    {
        return match (strtolower($account->driver)) {
            'bosta' => new BostaCourierDriver($account),
            'mock' => new MockCourierDriver($account),
            default => new MockCourierDriver($account),
        };
    }
}
