<?php

declare(strict_types=1);

namespace App\Http\Controllers\Affiliate\App;

use App\Domain\Orders\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Affiliate;
use App\Support\Location\Governorate;
use App\Support\Translate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use Lunar\Models\Order;
use Lunar\Models\Product;

class AppAnalyticsController extends Controller
{
    public function index(Request $request): Response
    {
        /** @var Affiliate $affiliate */
        $affiliate = Auth::guard('affiliate')->user();

        $allOrders = Order::query()
            ->where('affiliate_id', $affiliate->id)
            ->get();

        $totalOrders = $allOrders->count();
        $deliveredOrders = $allOrders->where('status', OrderStatus::DELIVERED->value)->count();
        $cancelledOrders = $allOrders->whereIn('status', [
            OrderStatus::CANCELLED_BY_CUSTOMER->value,
            OrderStatus::CANCELLED_NO_ANSWER->value,
        ])->count();
        $returnedOrders = $allOrders->whereIn('status', [
            OrderStatus::RETURN_REQUESTED->value,
            OrderStatus::RETURNING->value,
            OrderStatus::RETURNED->value,
        ])->count();

        $deliveryRate = $totalOrders > 0 ? round(($deliveredOrders / $totalOrders) * 100, 1) : 0.0;
        $cancellationRate = $totalOrders > 0 ? round(($cancelledOrders / $totalOrders) * 100, 1) : 0.0;
        $returnRate = $totalOrders > 0 ? round(($returnedOrders / $totalOrders) * 100, 1) : 0.0;

        $totalProfitMinor = 0;
        foreach ($allOrders as $order) {
            $snapshot = is_array($order->price_snapshot) ? $order->price_snapshot : json_decode($order->price_snapshot ?? '[]', true);
            if ($order->status === OrderStatus::DELIVERED->value) {
                $totalProfitMinor += (int) ($snapshot['affiliate_profit_minor'] ?? 0);
            }
        }

        // Breakdown by Product
        $productsMap = [];
        foreach ($allOrders as $order) {
            $snapshot = is_array($order->price_snapshot) ? $order->price_snapshot : json_decode($order->price_snapshot ?? '[]', true);
            $items = $snapshot['items'] ?? [];
            foreach ($items as $item) {
                $pId = (int) ($item['product_id'] ?? 0);
                if (! $pId) {
                    continue;
                }
                if (! isset($productsMap[$pId])) {
                    $productsMap[$pId] = [
                        'product_id' => $pId,
                        'product_name' => $item['product_name'] ?? __('منتج #:p_id', ['p_id' => $pId]),
                        'orders_count' => 0,
                        'delivered_count' => 0,
                        'returned_count' => 0,
                        'profit_minor' => 0,
                    ];
                }
                $productsMap[$pId]['orders_count']++;
                if ($order->status === OrderStatus::DELIVERED->value) {
                    $productsMap[$pId]['delivered_count']++;
                    $productsMap[$pId]['profit_minor'] += (int) ($item['line_profit_minor'] ?? 0);
                } elseif (in_array($order->status, [OrderStatus::RETURN_REQUESTED->value, OrderStatus::RETURNING->value, OrderStatus::RETURNED->value], true)) {
                    $productsMap[$pId]['returned_count']++;
                }
            }
        }

        $byProduct = collect($productsMap)->values()->map(function ($p) {
            $p['delivery_rate'] = $p['orders_count'] > 0 ? round(($p['delivered_count'] / $p['orders_count']) * 100, 1) : 0.0;
            $p['return_rate'] = $p['orders_count'] > 0 ? round(($p['returned_count'] / $p['orders_count']) * 100, 1) : 0.0;

            return $p;
        })->sortByDesc('orders_count')->values()->all();

        // Breakdown by Governorate
        $govMap = [];
        foreach ($allOrders as $order) {
            $snapshot = is_array($order->price_snapshot) ? $order->price_snapshot : json_decode($order->price_snapshot ?? '[]', true);
            $city = (string) ($snapshot['customer_city'] ?? __('غير محدد'));

            if (! isset($govMap[$city])) {
                $govMap[$city] = [
                    'governorate' => $city,
                    'orders_count' => 0,
                    'delivered_count' => 0,
                    'cancelled_count' => 0,
                ];
            }
            $govMap[$city]['orders_count']++;
            if ($order->status === OrderStatus::DELIVERED->value) {
                $govMap[$city]['delivered_count']++;
            } elseif (in_array($order->status, [OrderStatus::CANCELLED_BY_CUSTOMER->value, OrderStatus::CANCELLED_NO_ANSWER->value], true)) {
                $govMap[$city]['cancelled_count']++;
            }
        }

        $byGovernorate = collect($govMap)->values()->map(function ($g) {
            $g['delivery_rate'] = $g['orders_count'] > 0 ? round(($g['delivered_count'] / $g['orders_count']) * 100, 1) : 0.0;

            return $g;
        })->sortByDesc('orders_count')->values()->all();

        // Breakdown by Source / Channel
        $sourceMap = [
            'Direct / لوحة التحكم' => ['source' => Translate::t('Direct / لوحة التحكم'), 'orders_count' => 0, 'delivered_count' => 0],
            'Facebook' => ['source' => 'Facebook', 'orders_count' => 0, 'delivered_count' => 0],
            'TikTok' => ['source' => 'TikTok', 'orders_count' => 0, 'delivered_count' => 0],
            'Instagram' => ['source' => 'Instagram', 'orders_count' => 0, 'delivered_count' => 0],
            'Telegram / WhatsApp' => ['source' => 'Telegram / WhatsApp', 'orders_count' => 0, 'delivered_count' => 0],
        ];

        foreach ($allOrders as $order) {
            $snapshot = is_array($order->price_snapshot) ? $order->price_snapshot : json_decode($order->price_snapshot ?? '[]', true);
            $utm = strtolower((string) ($snapshot['utm_source'] ?? 'direct'));

            $key = 'Direct / لوحة التحكم';
            if (str_contains($utm, 'facebook') || str_contains($utm, 'fb')) {
                $key = 'Facebook';
            } elseif (str_contains($utm, 'tiktok') || str_contains($utm, 'tt')) {
                $key = 'TikTok';
            } elseif (str_contains($utm, 'instagram') || str_contains($utm, 'ig')) {
                $key = 'Instagram';
            } elseif (str_contains($utm, 'telegram') || str_contains($utm, 'whatsapp')) {
                $key = 'Telegram / WhatsApp';
            }

            $sourceMap[$key]['orders_count']++;
            if ($order->status === OrderStatus::DELIVERED->value) {
                $sourceMap[$key]['delivered_count']++;
            }
        }

        $bySource = collect($sourceMap)->values()->map(function ($s) {
            $s['delivery_rate'] = $s['orders_count'] > 0 ? round(($s['delivered_count'] / $s['orders_count']) * 100, 1) : 0.0;

            return $s;
        })->all();

        return Inertia::render('App/Analytics', [
            'overview' => [
                'total_orders' => $totalOrders,
                'delivered_orders' => $deliveredOrders,
                'cancelled_orders' => $cancelledOrders,
                'returned_orders' => $returnedOrders,
                'delivery_rate' => $deliveryRate,
                'cancellation_rate' => $cancellationRate,
                'return_rate' => $returnRate,
                'total_profit_minor' => $totalProfitMinor,
            ],
            'byProduct' => $byProduct,
            'byGovernorate' => $byGovernorate,
            'bySource' => $bySource,
        ]);
    }
}
