<?php

declare(strict_types=1);

namespace App\Domain\Checkout\Services;

use App\Domain\Checkout\Contracts\PaymentGatewayInterface;
use App\Domain\Checkout\DTOs\PaymentResult;
use Lunar\Models\Order;

class CodPaymentGateway implements PaymentGatewayInterface
{
    public function getIdentifier(): string
    {
        return 'cod';
    }

    public function getNameAr(): string
    {
        return __('الدفع نقدًا عند الاستلام (COD)');
    }

    /**
     * @param  array<string, mixed>  $paymentData
     */
    public function process(Order $order, array $paymentData = []): PaymentResult
    {
        return PaymentResult::cod();
    }
}
