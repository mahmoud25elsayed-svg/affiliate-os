<?php

declare(strict_types=1);

namespace App\Domain\Reports\Services;

use App\Domain\Affiliate\Settings\AffiliateProgramSettings;
use App\Domain\Finance\Services\PayoutTurnaroundCalculator;
use App\Domain\Orders\Enums\OrderStatus;
use App\Models\Affiliate;
use Lunar\Models\Order;

class StorePublicTrustMetricsService
{
    public function __construct(
        protected PayoutTurnaroundCalculator $payoutCalculator
    ) {}

    /**
     * Get public performance & trust badges for affiliates.
     *
     * @return array{
     *     enabled: bool,
     *     average_payout_time: string,
     *     delivery_success_rate: float,
     *     active_affiliates_count: int,
     *     delivered_orders_count: int
     * }
     */
    public function getMetrics(): array
    {
        $settings = AffiliateProgramSettings::load();
        $isEnabled = $settings->public_trust_metrics_enabled;

        $tenantId = function_exists('tenancy') && tenancy()->initialized ? (string) tenant('id') : null;

        $ordersQuery = Order::query();
        if ($tenantId) {
            $ordersQuery->where('tenant_id', $tenantId);
        }

        $allOrders = $ordersQuery->get();

        $shippedOrDelivered = $allOrders->filter(fn ($o) => in_array((string) $o->status, [
            OrderStatus::SHIPPED->value,
            OrderStatus::OUT_FOR_DELIVERY->value,
            OrderStatus::DELIVERED->value,
            OrderStatus::SETTLED->value,
            OrderStatus::RETURN_REQUESTED->value,
            OrderStatus::RETURNING->value,
            OrderStatus::RETURNED->value,
            OrderStatus::DELIVERY_FAILED->value,
        ], true));

        $deliveredOrders = $shippedOrDelivered->filter(fn ($o) => in_array((string) $o->status, [
            OrderStatus::DELIVERED->value,
            OrderStatus::SETTLED->value,
        ], true));

        $shippedCount = $shippedOrDelivered->count();
        $deliveredCount = $deliveredOrders->count();

        $deliverySuccessRate = $shippedCount > 0
            ? round(($deliveredCount / $shippedCount) * 100, 1)
            : 0.0;

        $affiliatesQuery = Affiliate::query()->where('status', 'active');
        if ($tenantId) {
            $affiliatesQuery->where('tenant_id', $tenantId);
        }
        $activeAffiliatesCount = $affiliatesQuery->count();

        $averagePayoutTime = $this->payoutCalculator->getAverageTurnaroundHuman();

        return [
            'enabled' => $isEnabled,
            'average_payout_time' => $averagePayoutTime,
            'delivery_success_rate' => $deliverySuccessRate,
            'active_affiliates_count' => $activeAffiliatesCount,
            'delivered_orders_count' => $deliveredCount,
        ];
    }
}
