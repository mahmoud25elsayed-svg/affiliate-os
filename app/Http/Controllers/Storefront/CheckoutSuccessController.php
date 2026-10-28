<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Domain\Tracking\Services\AdTrackingManager;
use App\Http\Controllers\Controller;
use App\Models\AdConversionEvent;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Lunar\Models\Order;
use Lunar\Models\OrderAddress;

class CheckoutSuccessController extends Controller
{
    public function __invoke(Request $request, string $reference, AdTrackingManager $trackingManager): View
    {
        /** @var Order $order */
        $order = Order::query()
            ->with(['lines.purchasable', 'addresses'])
            ->where('reference', $reference)
            ->firstOrFail();

        /** @var OrderAddress|null $shippingAddress */
        $shippingAddress = $order->addresses->where('type', 'shipping')->first();

        $eventId = $trackingManager->resolveOrderEventId($order, AdConversionEvent::EVENT_PURCHASE);

        // Server-side Conversions API dispatch (deduplicated by eventId with browser pixel)
        $trackingManager->trackOrderPurchase(
            order: $order,
            eventId: $eventId,
            clientContext: [
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'fbp' => $request->cookie('_fbp'),
                'fbc' => $request->cookie('_fbc'),
                'ttclid' => $request->cookie('ttclid'),
            ]
        );

        return view('storefront.checkout-success', [
            'order' => $order,
            'shippingAddress' => $shippingAddress,
            'priceSnapshot' => $order->price_snapshot ?? [],
            'eventId' => $eventId,
        ]);
    }
}
