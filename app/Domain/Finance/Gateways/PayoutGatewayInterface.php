<?php

declare(strict_types=1);

namespace App\Domain\Finance\Gateways;

use App\Models\PayoutRequest;

interface PayoutGatewayInterface
{
    public function supports(string $paymentMethod): bool;

    public function transfer(PayoutRequest $payoutRequest): PayoutGatewayResult;

    public function checkStatus(string $gatewayReference): PayoutGatewayStatus;
}
