<?php

declare(strict_types=1);

namespace App\Domain\Finance\Gateways;

use App\Models\PayoutRequest;

class ManualPayoutGateway implements PayoutGatewayInterface
{
    public function supports(string $paymentMethod): bool
    {
        return true;
    }

    public function transfer(PayoutRequest $payoutRequest): PayoutGatewayResult
    {
        return PayoutGatewayResult::successful(
            referenceNumber: $payoutRequest->reference_number ?? ('MANUAL-'.uniqid()),
            message: __('تم تسجيل التحويل اليدوي بنجاح')
        );
    }

    public function checkStatus(string $gatewayReference): PayoutGatewayStatus
    {
        return PayoutGatewayStatus::COMPLETED;
    }
}
