<?php

declare(strict_types=1);

namespace App\Domain\Reports\Services;

use App\Domain\Orders\Enums\OrderStatus;
use App\Models\DailyStoreMetric;
use App\Models\Tenant;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Lunar\DataTypes\Price;
use Lunar\Models\Order;

class DailyMetricsAggregator
{
    /**
     * Compute and persist daily aggregation metrics for a specific tenant on a given date.
     */
    public function aggregateForTenant(string|Tenant $tenant, CarbonInterface|string $date): DailyStoreMetric
    {
        $tenantId = $tenant instanceof Tenant ? (string) $tenant->id : (string) $tenant;
        $dateString = is_string($date) ? Carbon::parse($date)->toDateString() : $date->toDateString();

        $orders = Order::query()
            ->where('tenant_id', $tenantId)
            ->whereDate('created_at', $dateString)
            ->get();

        $ordersCount = $orders->count();
        $confirmedCount = 0;
        $deliveredCount = 0;
        $returnedCount = 0;
        $cancelledCount = 0;

        $grossRevenueMinor = 0;
        $affiliateCommissionMinor = 0;
        $shippingExpenseMinor = 0;
        $cogsMinor = 0;
        $taxMinor = 0;

        foreach ($orders as $order) {
            $status = (string) $order->status;

            // Confirmed cohort
            if (in_array($status, [
                OrderStatus::CONFIRMED->value,
                OrderStatus::RESERVED->value,
                OrderStatus::PACKED->value,
                OrderStatus::SHIPPED->value,
                OrderStatus::OUT_FOR_DELIVERY->value,
                OrderStatus::DELIVERED->value,
                OrderStatus::SETTLED->value,
                OrderStatus::RETURN_REQUESTED->value,
                OrderStatus::RETURNING->value,
                OrderStatus::RETURNED->value,
            ], true)) {
                $confirmedCount++;
            }

            // Delivered cohort
            if (in_array($status, [OrderStatus::DELIVERED->value, OrderStatus::SETTLED->value], true)) {
                $deliveredCount++;

                $orderTotal = $this->extractMinorAmount($order->getRawOriginal('total') ?? $order->total);
                $grossRevenueMinor += $orderTotal;

                $orderTax = $this->extractMinorAmount($order->getRawOriginal('tax_total') ?? $order->tax_total);
                $taxMinor += $orderTax;

                $orderAffiliateProfit = (int) ($order->affiliate_profit_minor ?? 0);
                $orderShipping = $this->extractMinorAmount($order->getRawOriginal('shipping_total') ?? $order->getAttribute('shipping_total'));

                $snapshot = is_array($order->price_snapshot)
                    ? $order->price_snapshot
                    : (is_string($order->price_snapshot) ? json_decode($order->price_snapshot, true) : []);

                if (is_array($snapshot)) {
                    if ($orderAffiliateProfit === 0 && isset($snapshot['affiliate_profit_minor'])) {
                        $orderAffiliateProfit = (int) $snapshot['affiliate_profit_minor'];
                    }
                    if ($orderShipping === 0 && isset($snapshot['shipping_cost_minor'])) {
                        $orderShipping = (int) $snapshot['shipping_cost_minor'];
                    }
                    if (isset($snapshot['wholesale_total_minor'])) {
                        $cogsMinor += (int) $snapshot['wholesale_total_minor'];
                    }
                }

                $affiliateCommissionMinor += $orderAffiliateProfit;
                $shippingExpenseMinor += $orderShipping;
            }

            // Returned cohort
            if (in_array($status, [
                OrderStatus::RETURN_REQUESTED->value,
                OrderStatus::RETURNING->value,
                OrderStatus::RETURNED->value,
                OrderStatus::DELIVERY_FAILED->value,
            ], true)) {
                $returnedCount++;
            }

            // Cancelled cohort
            if (in_array($status, [
                OrderStatus::CANCELLED_BY_CUSTOMER->value,
                OrderStatus::CANCELLED_NO_ANSWER->value,
            ], true) || str_starts_with($status, 'cancelled')) {
                $cancelledCount++;
            }
        }

        $netProfitMinor = $grossRevenueMinor - ($affiliateCommissionMinor + $shippingExpenseMinor + $cogsMinor + $taxMinor);

        $payload = [
            'orders_count' => $ordersCount,
            'confirmed_orders_count' => $confirmedCount,
            'delivered_orders_count' => $deliveredCount,
            'returned_orders_count' => $returnedCount,
            'cancelled_orders_count' => $cancelledCount,
            'gross_revenue_minor' => $grossRevenueMinor,
            'affiliate_commission_minor' => $affiliateCommissionMinor,
            'shipping_expense_minor' => $shippingExpenseMinor,
            'cogs_minor' => $cogsMinor,
            'tax_minor' => $taxMinor,
            'net_profit_minor' => $netProfitMinor,
        ];

        /** @var DailyStoreMetric|null $existing */
        $existing = DailyStoreMetric::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereDate('metric_date', $dateString)
            ->first();

        if ($existing) {
            $existing->update($payload);

            return $existing;
        }

        /** @var DailyStoreMetric $metric */
        $metric = DailyStoreMetric::withoutGlobalScopes()->create(array_merge([
            'tenant_id' => $tenantId,
            'metric_date' => $dateString,
        ], $payload));

        return $metric;
    }

    /**
     * Aggregate daily metrics across all tenants for a specified date.
     *
     * @return int Number of tenants aggregated
     */
    public function aggregateAllTenants(CarbonInterface|string $date): int
    {
        /** @var Collection<int, Tenant> $tenants */
        $tenants = Tenant::all();
        $count = 0;

        foreach ($tenants as $tenant) {
            $this->aggregateForTenant($tenant, $date);
            $count++;
        }

        return $count;
    }

    /**
     * Extract integer minor amount from model attribute.
     */
    protected function extractMinorAmount(mixed $value): int
    {
        if ($value instanceof Price) {
            return (int) $value->value;
        }

        return (int) ($value ?? 0);
    }
}
