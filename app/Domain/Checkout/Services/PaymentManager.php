<?php

declare(strict_types=1);

namespace App\Domain\Checkout\Services;

use App\Domain\Checkout\Contracts\PaymentGatewayInterface;
use InvalidArgumentException;

class PaymentManager
{
    /**
     * @var array<string, PaymentGatewayInterface>
     */
    protected array $gateways = [];

    public function __construct()
    {
        $this->register(new CodPaymentGateway);
    }

    public function register(PaymentGatewayInterface $gateway): self
    {
        $this->gateways[$gateway->getIdentifier()] = $gateway;

        return $this;
    }

    public function get(string $identifier): PaymentGatewayInterface
    {
        if (! isset($this->gateways[$identifier])) {
            throw new InvalidArgumentException("بوابة الدفع غير معروفة: {$identifier}");
        }

        return $this->gateways[$identifier];
    }

    /**
     * @return array<string, string>
     */
    public function getAvailableGateways(): array
    {
        $result = [];
        foreach ($this->gateways as $id => $gateway) {
            $result[$id] = $gateway->getNameAr();
        }

        return $result;
    }
}
