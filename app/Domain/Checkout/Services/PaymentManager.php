<?php

declare(strict_types=1);

namespace App\Domain\Checkout\Services;

use App\Domain\Checkout\Contracts\PaymentGatewayInterface;
use App\Domain\Checkout\Services\Gateways\FakePaymentGateway;
use App\Domain\Checkout\Services\Gateways\PaymobPaymentGateway;
use App\Models\TenantPaymentGateway;
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
        $this->register(new PaymobPaymentGateway);

        // The fake gateway's webhook secret is public, so in production anyone could forge "paid" callbacks.
        if (app()->environment(['local', 'testing'])) {
            $this->register(new FakePaymentGateway);
        }
    }

    public function register(PaymentGatewayInterface $gateway): self
    {
        $this->gateways[$gateway->getIdentifier()] = $gateway;

        return $this;
    }

    public function get(string $identifier): PaymentGatewayInterface
    {
        if (! isset($this->gateways[$identifier])) {
            throw new InvalidArgumentException(__('بوابة الدفع غير معروفة: :identifier', ['identifier' => $identifier]));
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

    /**
     * Gateways active for the current tenant.
     *
     * @return array<string, string>
     */
    public function getActiveGatewaysForTenant(): array
    {
        $result = [];
        if (isset($this->gateways['cod'])) {
            $result['cod'] = $this->gateways['cod']->getNameAr();
        }

        try {
            $enabledGateways = TenantPaymentGateway::query()
                ->where('is_enabled', true)
                ->pluck('gateway')
                ->all();

            foreach ($enabledGateways as $gatewayId) {
                if (is_string($gatewayId) && isset($this->gateways[$gatewayId])) {
                    $result[$gatewayId] = $this->gateways[$gatewayId]->getNameAr();
                }
            }
        } catch (\Throwable) {
            // Fallback during initial migrations or without tenant context
        }

        return $result;
    }
}
