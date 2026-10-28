<?php

declare(strict_types=1);

namespace App\Domain\Finance\Gateways;

enum PayoutGatewayStatus: string
{
    case PENDING = 'pending';
    case COMPLETED = 'completed';
    case FAILED = 'failed';
}
