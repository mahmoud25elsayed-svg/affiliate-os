<?php

declare(strict_types=1);

namespace App\Livewire\Storefront;

use App\Models\Customer;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Lunar\Models\Order;

#[Layout('layouts.storefront')]
class CustomerOrdersPage extends Component
{
    /**
     * @return Collection<int, Order>
     */
    public function getOrdersProperty(): Collection
    {
        /** @var Customer|null $customer */
        $customer = Auth::guard('customer')->user();
        if (! $customer) {
            return new Collection;
        }

        /** @var Collection<int, Order> $orders */
        $orders = Order::query()
            ->where(function ($q) use ($customer) {
                $q->where('storefront_customer_id', $customer->id);
                if ($customer->phone_hash) {
                    $q->orWhere('customer_phone_hash', $customer->phone_hash);
                }
            })
            ->with(['lines.purchasable'])
            ->latest()
            ->get();

        return $orders;
    }

    public function render(): View
    {
        return view('livewire.storefront.customer-orders-page', [
            'orders' => $this->getOrdersProperty(),
            'pageTitle' => 'طلباتي',
        ]);
    }
}
