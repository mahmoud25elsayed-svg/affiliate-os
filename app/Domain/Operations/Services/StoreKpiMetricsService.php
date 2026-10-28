<?php

declare(strict_types=1);

namespace App\Domain\Operations\Services;

use App\Domain\Orders\Enums\OrderStatus;
use App\Models\Supplier;
use Carbon\Carbon;
use Lunar\DataTypes\Price;
use Lunar\Models\Order;
use Lunar\Models\ProductVariant;

class StoreKpiMetricsService
{
    /**
     * Calculate comprehensive operational KPIs for the active tenant.
     *
     * @return array<string, mixed>
     */
    public function getMetrics(string $period = 'all'): array
    {
        $tenantId = function_exists('tenancy') && tenancy()->initialized ? (string) tenant('id') : null;

        $ordersQuery = Order::query();
        if ($tenantId) {
            $ordersQuery->where('tenant_id', $tenantId);
        }

        // Apply period filter
        if ($period === 'today') {
            $ordersQuery->whereDate('created_at', Carbon::today());
        } elseif ($period === 'week') {
            $ordersQuery->where('created_at', '>=', Carbon::now()->subDays(7));
        } elseif ($period === 'month') {
            $ordersQuery->where('created_at', '>=', Carbon::now()->subDays(30));
        }

        $allOrders = $ordersQuery->get();
        $totalOrdersCount = $allOrders->count();

        // 1. Order Status Counts
        $confirmedCount = 0;
        $deliveredCount = 0;
        $shippedTotalCount = 0;
        $returnedCount = 0;
        $cancelledCount = 0;
        $pendingConfirmationCount = 0;

        $grossRevenueMinor = 0;
        $affiliateCommissionMinor = 0;
        $shippingExpenseMinor = 0;

        foreach ($allOrders as $order) {
            $status = (string) $order->status;

            if ($status === OrderStatus::PENDING_CONFIRMATION->value) {
                $pendingConfirmationCount++;
            }

            // Confirmed includes anything confirmed or moved past confirmation
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

            // Shipped cohort
            if (in_array($status, [
                OrderStatus::SHIPPED->value,
                OrderStatus::OUT_FOR_DELIVERY->value,
                OrderStatus::DELIVERED->value,
                OrderStatus::SETTLED->value,
                OrderStatus::RETURN_REQUESTED->value,
                OrderStatus::RETURNING->value,
                OrderStatus::RETURNED->value,
                OrderStatus::DELIVERY_FAILED->value,
            ], true)) {
                $shippedTotalCount++;
            }

            // Delivered
            if (in_array($status, [OrderStatus::DELIVERED->value, OrderStatus::SETTLED->value], true)) {
                $deliveredCount++;

                /** @var mixed $rawTotal */
                $rawTotal = $order->total;
                $total = $rawTotal instanceof Price ? (int) $rawTotal->value : (int) $rawTotal;
                $grossRevenueMinor += $total;

                $snapshot = is_string($order->price_snapshot) ? json_decode($order->price_snapshot, true) : (array) ($order->price_snapshot ?? []);
                if (is_array($snapshot) && isset($snapshot['affiliate_profit_minor'])) {
                    $affiliateCommissionMinor += (int) $snapshot['affiliate_profit_minor'];
                }
                if (is_array($snapshot) && isset($snapshot['shipping_cost_minor'])) {
                    $shippingExpenseMinor += (int) $snapshot['shipping_cost_minor'];
                }
            }

            // Returned
            if (in_array($status, [
                OrderStatus::RETURN_REQUESTED->value,
                OrderStatus::RETURNING->value,
                OrderStatus::RETURNED->value,
                OrderStatus::DELIVERY_FAILED->value,
            ], true)) {
                $returnedCount++;
            }

            // Cancelled
            if (in_array($status, [
                OrderStatus::CANCELLED_BY_CUSTOMER->value,
                OrderStatus::CANCELLED_NO_ANSWER->value,
            ], true) || str_starts_with($status, 'cancelled')) {
                $cancelledCount++;
            }
        }

        // 2. Conversion and Operational Rates
        $confirmationRate = $totalOrdersCount > 0 ? round(($confirmedCount / $totalOrdersCount) * 100, 1) : 0.0;
        $deliveryRate = $shippedTotalCount > 0 ? round(($deliveredCount / $shippedTotalCount) * 100, 1) : 0.0;
        $returnRate = $shippedTotalCount > 0 ? round(($returnedCount / $shippedTotalCount) * 100, 1) : 0.0;

        // 3. Stock Health & Out-of-stock Alerts
        $variantsQuery = ProductVariant::query();
        if ($tenantId) {
            $variantsQuery->where('tenant_id', $tenantId);
        }
        $variants = $variantsQuery->get();

        $stockoutCount = $variants->where('stock', '<=', 0)->count();
        $lowStockCount = $variants->where('stock', '>', 0)->where('stock', '<=', 5)->count();

        // 4. Suppliers Count
        $suppliersQuery = Supplier::query();
        if ($tenantId) {
            $suppliersQuery->where('tenant_id', $tenantId);
        }
        $suppliersCount = $suppliersQuery->count();

        // 5. Net Margin
        $netMerchantMarginMinor = $grossRevenueMinor - ($affiliateCommissionMinor + $shippingExpenseMinor);

        return [
            'period' => $period,
            'total_orders' => $totalOrdersCount,
            'pending_confirmation_orders' => $pendingConfirmationCount,
            'confirmed_orders' => $confirmedCount,
            'shipped_orders' => $shippedTotalCount,
            'delivered_orders' => $deliveredCount,
            'returned_orders' => $returnedCount,
            'cancelled_orders' => $cancelledCount,
            'confirmation_rate' => $confirmationRate,
            'delivery_rate' => $deliveryRate,
            'return_rate' => $returnRate,
            'stockout_count' => $stockoutCount,
            'low_stock_count' => $lowStockCount,
            'suppliers_count' => $suppliersCount,
            'gross_revenue_minor' => $grossRevenueMinor,
            'affiliate_commission_minor' => $affiliateCommissionMinor,
            'shipping_expense_minor' => $shippingExpenseMinor,
            'net_merchant_margin_minor' => $netMerchantMarginMinor,
        ];
    }
}
