<?php

declare(strict_types=1);

namespace App\Http\Controllers\Affiliate\App;

use App\Domain\Affiliate\Settings\AffiliateProgramSettings;
use App\Domain\Finance\Services\PayoutTurnaroundCalculator;
use App\Domain\Orders\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Affiliate;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use Lunar\Models\Order;
use Lunar\Models\Product;

class AppDashboardController extends Controller
{
    public function __invoke(PayoutTurnaroundCalculator $turnaroundCalculator): Response
    {
        /** @var Affiliate $affiliate */
        $affiliate = Auth::guard('affiliate')->user();
        $settings = AffiliateProgramSettings::load();

        $ordersQuery = Order::query()->where('affiliate_id', $affiliate->id);

        $totalOrders = (clone $ordersQuery)->count();
        $todayOrders = (clone $ordersQuery)->whereDate('created_at', today())->count();
        $deliveredOrders = (clone $ordersQuery)->where('status', OrderStatus::DELIVERED->value)->count();
        $cancelledOrders = (clone $ordersQuery)->whereIn('status', [
            OrderStatus::CANCELLED_BY_CUSTOMER->value,
            OrderStatus::CANCELLED_NO_ANSWER->value,
        ])->count();
        $returnedOrders = (clone $ordersQuery)->whereIn('status', [
            OrderStatus::RETURN_REQUESTED->value,
            OrderStatus::RETURNING->value,
            OrderStatus::RETURNED->value,
        ])->count();

        $deliveryRate = $totalOrders > 0 ? round(($deliveredOrders / $totalOrders) * 100, 1) : 0.0;

        $recentOrders = (clone $ordersQuery)
            ->latest('id')
            ->take(5)
            ->get()
            ->map(function ($order) {
                /** @var Order $order */
                $snapshot = is_array($order->price_snapshot) ? $order->price_snapshot : json_decode($order->price_snapshot ?? '[]', true);

                return [
                    'id' => $order->id,
                    'reference' => $order->reference ?? (string) $order->id,
                    'status' => $order->status,
                    'customer_city' => $snapshot['customer_city'] ?? 'غير محدد',
                    'profit_minor' => (int) ($snapshot['affiliate_profit_minor'] ?? 0),
                    'total_minor' => (int) ($order->total ?? 0),
                    'created_at' => $order->created_at?->format('Y-m-d H:i') ?? '',
                ];
            });

        // Low stock products alert
        $stockAlerts = Product::query()
            ->with(['variants'])
            ->take(5)
            ->get()
            ->map(function ($p) {
                $stock = $p->variants->sum('stock');

                return [
                    'id' => $p->id,
                    'name' => $p->translateAttribute('name') ?? "منتج #{$p->id}",
                    'stock' => $stock,
                    'is_low' => $stock <= 5,
                ];
            })
            ->filter(fn ($p) => $p['is_low'])
            ->values();

        return Inertia::render('App/Dashboard', [
            'affiliate' => [
                'id' => $affiliate->id,
                'name' => $affiliate->name,
                'code' => $affiliate->code,
                'phone' => $affiliate->phone,
                'status' => $affiliate->status,
                'kyc_status' => $affiliate->kyc_status,
                'can_withdraw' => $affiliate->canWithdraw(),
                'is_frozen' => $affiliate->isPayoutFrozen(),
                'frozen_until' => $affiliate->payouts_frozen_until?->format('Y-m-d H:i'),
            ],
            'balances' => [
                'available' => $affiliate->getAvailableBalance(),
                'pending' => $affiliate->getPendingBalance(),
                'locked' => $affiliate->getLockedBalance(),
                'withdrawn' => $affiliate->getWithdrawnBalance(),
                'debt' => $affiliate->getDebtMinor(),
                'has_debt' => $affiliate->hasDebt(),
            ],
            'stats' => [
                'total_orders' => $totalOrders,
                'today_orders' => $todayOrders,
                'delivered_orders' => $deliveredOrders,
                'cancelled_orders' => $cancelledOrders,
                'returned_orders' => $returnedOrders,
                'delivery_rate' => $deliveryRate,
            ],
            'settings' => [
                'min_payout_minor' => $settings->min_payout_minor,
                'return_window_days' => $settings->return_window_days,
                'daily_limit' => $settings->default_daily_order_limit,
                'payout_days' => $settings->payout_days,
            ],
            'recentOrders' => $recentOrders,
            'stockAlerts' => $stockAlerts,
            'averageTurnaround' => $turnaroundCalculator->getAverageTurnaroundHuman(),
        ]);
    }
}
