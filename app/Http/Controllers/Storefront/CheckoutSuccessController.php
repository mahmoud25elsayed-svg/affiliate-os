<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Lunar\Models\Order;
use Lunar\Models\OrderAddress;

class CheckoutSuccessController extends Controller
{
    public function __invoke(string $reference): View
    {
        /** @var Order $order */
        $order = Order::query()
            ->with(['lines', 'addresses'])
            ->where('reference', $reference)
            ->firstOrFail();

        /** @var OrderAddress|null $shippingAddress */
        $shippingAddress = $order->addresses->where('type', 'shipping')->first();

        return view('storefront.checkout-success', [
            'order' => $order,
            'shippingAddress' => $shippingAddress,
            'priceSnapshot' => $order->price_snapshot ?? [],
        ]);
    }
}
