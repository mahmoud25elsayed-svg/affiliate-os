<?php

declare(strict_types=1);

namespace App\Domain\Checkout\Contracts;

use App\Domain\Checkout\DTOs\PaymentResult;
use Lunar\Models\Order;

interface PaymentGatewayInterface
{
    public function getIdentifier(): string;

    public function getNameAr(): string;

    /**
     * @param  array<string, mixed>  $paymentData
     */
    public function process(Order $order, array $paymentData = []): PaymentResult;
}
