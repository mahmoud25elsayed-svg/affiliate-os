<?php

declare(strict_types=1);

namespace App\Domain\Reports\Services;

use App\Domain\Orders\Enums\OrderStatus;
use App\Models\Affiliate;
use App\Models\DailyStoreMetric;
use App\Models\InventoryBalance;
use App\Models\OrderEvent;
use App\Support\Enums\ReasonCode;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Lunar\DataTypes\Price;
use Lunar\Models\Order;
use Lunar\Models\OrderAddress;
use Lunar\Models\OrderLine;
use Lunar\Models\Product;
use Lunar\Models\ProductVariant;

class MerchantAnalyticsService
{
    public function __construct(
        protected DailyMetricsAggregator $aggregator
    ) {}

    /**
     * Resolve date bounds from a preset or custom range.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    public function parseDateRange(string $preset = 'last_30_days', ?string $from = null, ?string $to = null): array
    {
        if ($preset === 'custom' && $from && $to) {
            $startDate = Carbon::parse($from)->startOfDay();
            $endDate = Carbon::parse($to)->endOfDay();

            return [$startDate, $endDate];
        }

        return match ($preset) {
            'today' => [Carbon::today()->startOfDay(), Carbon::today()->endOfDay()],
            'yesterday' => [Carbon::yesterday()->startOfDay(), Carbon::yesterday()->endOfDay()],
            'last_7_days' => [Carbon::today()->subDays(6)->startOfDay(), Carbon::today()->endOfDay()],
            'this_month' => [Carbon::now()->startOfMonth()->startOfDay(), Carbon::now()->endOfDay()],
            'last_month' => [Carbon::now()->subMonth()->startOfMonth()->startOfDay(), Carbon::now()->subMonth()->endOfMonth()],
            default => [Carbon::today()->subDays(29)->startOfDay(), Carbon::today()->endOfDay()],
        };
    }

    /**
     * Resolve the active tenant ID.
     */
    protected function resolveTenantId(?string $explicitTenantId = null): ?string
    {
        if ($explicitTenantId !== null) {
            return $explicitTenantId;
        }

        return function_exists('tenancy') && tenancy()->initialized ? (string) tenant('id') : null;
    }

    /**
     * Get aggregate overview metrics using pre-aggregated rollup records.
     * If the date range includes today, today's metrics are freshly aggregated or merged.
     *
     * @return array<string, mixed>
     */
    public function getOverview(string $preset = 'last_30_days', ?string $from = null, ?string $to = null, ?string $tenantId = null): array
    {
        [$startDate, $endDate] = $this->parseDateRange($preset, $from, $to);
        $tId = $this->resolveTenantId($tenantId);

        // If range covers today, refresh today's daily aggregate so real-time numbers are accurate
        if ($tId && $endDate->isToday()) {
            $this->aggregator->aggregateForTenant($tId, Carbon::today());
        }

        $query = DailyStoreMetric::withoutGlobalScopes();
        if ($tId) {
            $query->where('tenant_id', $tId);
        }

        $metrics = $query->whereDate('metric_date', '>=', $startDate->toDateString())
            ->whereDate('metric_date', '<=', $endDate->toDateString())
            ->get();

        $totalOrders = (int) $metrics->sum('orders_count');
        $confirmedOrders = (int) $metrics->sum('confirmed_orders_count');
        $deliveredOrders = (int) $metrics->sum('delivered_orders_count');
        $returnedOrders = (int) $metrics->sum('returned_orders_count');
        $cancelledOrders = (int) $metrics->sum('cancelled_orders_count');

        $grossRevenueMinor = (int) $metrics->sum('gross_revenue_minor');
        $netProfitMinor = (int) $metrics->sum('net_profit_minor');
        $affiliateCommissionMinor = (int) $metrics->sum('affiliate_commission_minor');
        $shippingExpenseMinor = (int) $metrics->sum('shipping_expense_minor');
        $cogsMinor = (int) $metrics->sum('cogs_minor');
        $taxMinor = (int) $metrics->sum('tax_minor');

        $deliveryRate = $totalOrders > 0 ? round(($deliveredOrders / $totalOrders) * 100, 1) : 0.0;
        $returnRate = $totalOrders > 0 ? round(($returnedOrders / $totalOrders) * 100, 1) : 0.0;
        $aovMinor = $deliveredOrders > 0 ? (int) round($grossRevenueMinor / $deliveredOrders) : 0;

        return [
            'preset' => $preset,
            'from' => $startDate->toDateString(),
            'to' => $endDate->toDateString(),
            'orders_count' => $totalOrders,
            'confirmed_orders_count' => $confirmedOrders,
            'delivered_orders_count' => $deliveredOrders,
            'returned_orders_count' => $returnedOrders,
            'cancelled_orders_count' => $cancelledOrders,
            'delivery_rate' => $deliveryRate,
            'return_rate' => $returnRate,
            'gross_revenue_minor' => $grossRevenueMinor,
            'net_profit_minor' => $netProfitMinor,
            'affiliate_commission_minor' => $affiliateCommissionMinor,
            'shipping_expense_minor' => $shippingExpenseMinor,
            'cogs_minor' => $cogsMinor,
            'tax_minor' => $taxMinor,
            'aov_minor' => $aovMinor,
            'daily_records' => $metrics->sortBy('metric_date')->values(),
        ];
    }

    /**
     * Get top selling products ranked by revenue or quantity with return rates.
     *
     * @return Collection<int, array{product_id: int|null, name: string, identifier: string, total_units_ordered: int, delivered_units: int, returned_units: int, total_revenue_minor: int, return_rate: float}>
     */
    public function getTopProducts(string $preset = 'last_30_days', int $limit = 10, ?string $from = null, ?string $to = null, ?string $tenantId = null): Collection
    {
        [$startDate, $endDate] = $this->parseDateRange($preset, $from, $to);
        $tId = $this->resolveTenantId($tenantId);

        $ordersQuery = Order::query()
            ->whereBetween('created_at', [$startDate, $endDate]);

        if ($tId) {
            $ordersQuery->where('tenant_id', $tId);
        }

        $orders = $ordersQuery->get();
        $orderIds = $orders->pluck('id')->all();

        if (empty($orderIds)) {
            return collect();
        }

        $lines = OrderLine::whereIn('order_id', $orderIds)->get();
        $orderStatusMap = $orders->pluck('status', 'id')->all();

        $productStats = [];

        foreach ($lines as $line) {
            $productId = null;
            $productName = $line->description ?? 'منتج غير معروف';

            if ($line->purchasable_type === ProductVariant::class && $line->purchasable_id) {
                /** @var ProductVariant|null $variant */
                $variant = ProductVariant::find($line->purchasable_id);
                if ($variant && $variant->product_id) {
                    $productId = (int) $variant->product_id;
                    $product = Product::find($variant->product_id);
                    if ($product && isset($product->attribute_data['name'])) {
                        $productName = (string) ($product->translateAttribute('name') ?? $productName);
                    }
                }
            }

            $key = $productId ?? $line->purchasable_id ?? $line->id;
            if (! isset($productStats[$key])) {
                $productStats[$key] = [
                    'product_id' => $productId,
                    'name' => $productName,
                    'identifier' => $line->identifier ?? '',
                    'total_units_ordered' => 0,
                    'delivered_units' => 0,
                    'returned_units' => 0,
                    'total_revenue_minor' => 0,
                ];
            }

            $qty = (int) $line->quantity;
            $orderStatus = $orderStatusMap[$line->order_id] ?? '';

            $productStats[$key]['total_units_ordered'] += $qty;

            if (in_array($orderStatus, [OrderStatus::DELIVERED->value, OrderStatus::SETTLED->value], true)) {
                $productStats[$key]['delivered_units'] += $qty;
                $lineTotal = $this->extractMinorAmount($line->getRawOriginal('total') ?? $line->total);
                $productStats[$key]['total_revenue_minor'] += $lineTotal;
            }

            if (in_array($orderStatus, [
                OrderStatus::RETURN_REQUESTED->value,
                OrderStatus::RETURNING->value,
                OrderStatus::RETURNED->value,
                OrderStatus::DELIVERY_FAILED->value,
            ], true)) {
                $productStats[$key]['returned_units'] += $qty;
            }
        }

        return collect($productStats)
            ->map(function ($stat) {
                $ordered = $stat['total_units_ordered'];
                $stat['return_rate'] = $ordered > 0 ? round(($stat['returned_units'] / $ordered) * 100, 1) : 0.0;

                return $stat;
            })
            ->sortByDesc('total_revenue_minor')
            ->take($limit)
            ->values();
    }

    /**
     * Get top affiliates ranked by delivered revenue and volume.
     *
     * @return Collection<int, array{affiliate_id: int|string, name: string, phone: string|null, code: string, orders_count: int, delivered_count: int, delivery_rate: float, revenue_minor: int, commission_minor: int}>
     */
    public function getTopAffiliates(string $preset = 'last_30_days', int $limit = 10, ?string $from = null, ?string $to = null, ?string $tenantId = null): Collection
    {
        [$startDate, $endDate] = $this->parseDateRange($preset, $from, $to);
        $tId = $this->resolveTenantId($tenantId);

        $query = Order::query()
            ->whereNotNull('affiliate_id')
            ->whereBetween('created_at', [$startDate, $endDate]);

        if ($tId) {
            $query->where('tenant_id', $tId);
        }

        $orders = $query->get();
        if ($orders->isEmpty()) {
            return collect();
        }

        $affiliateIds = $orders->pluck('affiliate_id')->unique()->all();
        $affiliates = Affiliate::withoutGlobalScopes()->whereIn('id', $affiliateIds)->get()->keyBy('id');

        $grouped = $orders->groupBy('affiliate_id');
        $results = [];

        foreach ($grouped as $affiliateId => $affiliateOrders) {
            /** @var Affiliate|null $affiliate */
            $affiliate = $affiliates->get((int) $affiliateId);
            $totalOrders = $affiliateOrders->count();

            $deliveredOrders = $affiliateOrders->filter(fn ($o) => in_array((string) $o->status, [OrderStatus::DELIVERED->value, OrderStatus::SETTLED->value], true));
            $deliveredCount = $deliveredOrders->count();

            $revenueMinor = 0;
            $commissionMinor = 0;

            foreach ($deliveredOrders as $order) {
                $revenueMinor += $this->extractMinorAmount($order->getRawOriginal('total') ?? $order->total);
                $commissionMinor += (int) ($order->affiliate_profit_minor ?? 0);
            }

            $deliveryRate = $totalOrders > 0 ? round(($deliveredCount / $totalOrders) * 100, 1) : 0.0;

            $results[] = [
                'affiliate_id' => $affiliateId,
                'name' => $affiliate ? $affiliate->name : "مسوق #{$affiliateId}",
                'phone' => $affiliate ? $affiliate->phone : '',
                'code' => $affiliate ? $affiliate->code : '',
                'orders_count' => $totalOrders,
                'delivered_count' => $deliveredCount,
                'delivery_rate' => $deliveryRate,
                'revenue_minor' => $revenueMinor,
                'commission_minor' => $commissionMinor,
            ];
        }

        return collect($results)
            ->sortByDesc('revenue_minor')
            ->take($limit)
            ->values();
    }

    /**
     * Get distribution across Egyptian governorates (Cairo, Giza, Alexandria, etc.)
     *
     * @return Collection<int, array{governorate: string, orders_count: int, delivered_count: int, returned_count: int, revenue_minor: int, delivery_rate: float, return_rate: float}>
     */
    public function getGovernoratesBreakdown(string $preset = 'last_30_days', ?string $from = null, ?string $to = null, ?string $tenantId = null): Collection
    {
        [$startDate, $endDate] = $this->parseDateRange($preset, $from, $to);
        $tId = $this->resolveTenantId($tenantId);

        $ordersQuery = Order::query()->whereBetween('created_at', [$startDate, $endDate]);
        if ($tId) {
            $ordersQuery->where('tenant_id', $tId);
        }

        $orders = $ordersQuery->get();
        if ($orders->isEmpty()) {
            return collect();
        }

        $orderIds = $orders->pluck('id')->all();
        $addresses = OrderAddress::whereIn('order_id', $orderIds)
            ->where('type', 'shipping')
            ->get()
            ->keyBy('order_id');

        $govStats = [];

        foreach ($orders as $order) {
            /** @var OrderAddress|null $addr */
            $addr = $addresses->get($order->id);
            $governorate = $addr ? ($addr->state ?: $addr->city ?: 'أخرى') : 'أخرى';
            $governorate = trim($governorate);

            if (! isset($govStats[$governorate])) {
                $govStats[$governorate] = [
                    'governorate' => $governorate,
                    'orders_count' => 0,
                    'delivered_count' => 0,
                    'returned_count' => 0,
                    'revenue_minor' => 0,
                ];
            }

            $govStats[$governorate]['orders_count']++;
            $status = (string) $order->status;

            if (in_array($status, [OrderStatus::DELIVERED->value, OrderStatus::SETTLED->value], true)) {
                $govStats[$governorate]['delivered_count']++;
                $govStats[$governorate]['revenue_minor'] += $this->extractMinorAmount($order->getRawOriginal('total') ?? $order->total);
            }

            if (in_array($status, [
                OrderStatus::RETURN_REQUESTED->value,
                OrderStatus::RETURNING->value,
                OrderStatus::RETURNED->value,
                OrderStatus::DELIVERY_FAILED->value,
            ], true)) {
                $govStats[$governorate]['returned_count']++;
            }
        }

        return collect($govStats)
            ->map(function ($row) {
                $total = $row['orders_count'];
                $row['delivery_rate'] = $total > 0 ? round(($row['delivered_count'] / $total) * 100, 1) : 0.0;
                $row['return_rate'] = $total > 0 ? round(($row['returned_count'] / $total) * 100, 1) : 0.0;

                return $row;
            })
            ->sortByDesc('orders_count')
            ->values();
    }

    /**
     * Get return rates and detailed reasons breakdown.
     *
     * @return array<string, mixed>
     */
    public function getReturnsBreakdown(string $preset = 'last_30_days', ?string $from = null, ?string $to = null, ?string $tenantId = null): array
    {
        [$startDate, $endDate] = $this->parseDateRange($preset, $from, $to);
        $tId = $this->resolveTenantId($tenantId);

        $ordersQuery = Order::query()->whereBetween('created_at', [$startDate, $endDate]);
        if ($tId) {
            $ordersQuery->where('tenant_id', $tId);
        }

        $allOrders = $ordersQuery->get();
        $totalOrdersCount = $allOrders->count();

        $returnedOrders = $allOrders->filter(fn ($o) => in_array((string) $o->status, [
            OrderStatus::RETURN_REQUESTED->value,
            OrderStatus::RETURNING->value,
            OrderStatus::RETURNED->value,
            OrderStatus::DELIVERY_FAILED->value,
        ], true));

        $returnedCount = $returnedOrders->count();
        $overallReturnRate = $totalOrdersCount > 0 ? round(($returnedCount / $totalOrdersCount) * 100, 1) : 0.0;

        // Query OrderEvents for return reasons
        $orderIds = $returnedOrders->pluck('id')->all();
        $eventsQuery = OrderEvent::whereIn('order_id', $orderIds)
            ->whereIn('to_status', [
                OrderStatus::RETURN_REQUESTED->value,
                OrderStatus::RETURNING->value,
                OrderStatus::RETURNED->value,
                OrderStatus::DELIVERY_FAILED->value,
            ]);

        $events = $eventsQuery->get();

        $reasonLabels = [
            'refused_at_doorstep' => 'رفض الاستلام عند الباب',
            'customer_unreachable' => 'العميل لا يجيب الهاتف / مغلق',
            'long_delivery_time' => 'تأخر مفرط في التوصيل من شركة الشحن',
            'wrong_item_sent' => 'استلام منتج أو مقاس غير مطابق',
            'defective_product' => 'عيب صناعة أو تلف أثناء الشحن',
            'not_as_described' => 'المنتج غير مطابق للوصف والصور',
            'customer_changed_mind' => 'تراجع العميل عن الشراء',
            'other' => 'أسباب أخرى عامة',
        ];

        $reasonsCounts = [];
        foreach ($reasonLabels as $code => $label) {
            $reasonsCounts[$code] = [
                'code' => $code,
                'label' => $label,
                'count' => 0,
                'percentage' => 0.0,
            ];
        }

        foreach ($returnedOrders as $order) {
            $event = $events->firstWhere('order_id', $order->id);
            $rawCode = $event?->reason_code;
            $reasonCode = $rawCode instanceof ReasonCode
                ? $rawCode->value
                : 'other';

            if (! isset($reasonsCounts[$reasonCode])) {
                $reasonCode = 'other';
            }

            $reasonsCounts[$reasonCode]['count']++;
        }

        foreach ($reasonsCounts as $code => &$item) {
            $item['percentage'] = $returnedCount > 0 ? round(($item['count'] / $returnedCount) * 100, 1) : 0.0;
        }
        unset($item);

        return [
            'total_orders' => $totalOrdersCount,
            'returned_count' => $returnedCount,
            'return_rate' => $overallReturnRate,
            'reasons' => array_values(collect($reasonsCounts)->sortByDesc('count')->all()),
        ];
    }

    /**
     * Get inventory health, stockouts, valuation, and turnover metrics.
     *
     * @return array<string, mixed>
     */
    public function getInventoryReport(?string $tenantId = null): array
    {
        $tId = $this->resolveTenantId($tenantId);

        $variantsQuery = ProductVariant::query();
        if ($tId) {
            $variantsQuery->where('tenant_id', $tId);
        }

        $variants = $variantsQuery->get();
        $totalVariants = $variants->count();

        $stockoutCount = $variants->where('stock', '<=', 0)->count();
        $lowStockCount = $variants->where('stock', '>', 0)->where('stock', '<=', 5)->count();
        $healthyStockCount = $variants->where('stock', '>', 5)->count();

        $totalUnitsOnHand = (int) $variants->sum('stock');

        // Calculate inventory cost valuation (wholesale price)
        $balancesQuery = InventoryBalance::withoutGlobalScopes();
        if ($tId) {
            $balancesQuery->where('tenant_id', $tId);
        }
        $balances = $balancesQuery->get();

        $reservedUnits = (int) $balances->sum('reserved');

        // Estimate inventory turnover in last 30 days
        $ordersLast30 = Order::query()
            ->where('created_at', '>=', Carbon::now()->subDays(30))
            ->when($tId, fn ($q) => $q->where('tenant_id', $tId))
            ->whereIn('status', [OrderStatus::DELIVERED->value, OrderStatus::SETTLED->value])
            ->pluck('id');

        $unitsSoldLast30 = (int) OrderLine::whereIn('order_id', $ordersLast30)->sum('quantity');

        $averageInventory = max(1, $totalUnitsOnHand);
        $turnoverRate = round($unitsSoldLast30 / $averageInventory, 2);

        return [
            'total_skus' => $totalVariants,
            'total_units_on_hand' => $totalUnitsOnHand,
            'reserved_units' => $reservedUnits,
            'available_units' => max(0, $totalUnitsOnHand - $reservedUnits),
            'stockout_skus' => $stockoutCount,
            'low_stock_skus' => $lowStockCount,
            'healthy_skus' => $healthyStockCount,
            'units_sold_30_days' => $unitsSoldLast30,
            'turnover_rate' => $turnoverRate,
        ];
    }

    /**
     * Get tax breakdown and total VAT collected.
     *
     * @return array<string, mixed>
     */
    public function getTaxReport(string $preset = 'last_30_days', ?string $from = null, ?string $to = null, ?string $tenantId = null): array
    {
        $overview = $this->getOverview($preset, $from, $to, $tenantId);

        $grossRevenueMinor = (int) $overview['gross_revenue_minor'];
        $taxCollectedMinor = (int) $overview['tax_minor'];

        $effectiveTaxRate = $grossRevenueMinor > 0 ? round(($taxCollectedMinor / $grossRevenueMinor) * 100, 2) : 0.0;

        return [
            'preset' => $preset,
            'from' => $overview['from'],
            'to' => $overview['to'],
            'gross_revenue_minor' => $grossRevenueMinor,
            'tax_collected_minor' => $taxCollectedMinor,
            'effective_tax_rate' => $effectiveTaxRate,
            'delivered_orders_count' => $overview['delivered_orders_count'],
        ];
    }

    protected function extractMinorAmount(mixed $value): int
    {
        if ($value instanceof Price) {
            return (int) $value->value;
        }

        return (int) ($value ?? 0);
    }
}
