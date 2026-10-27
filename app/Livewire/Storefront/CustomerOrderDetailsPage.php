<?php

declare(strict_types=1);

namespace App\Livewire\Storefront;

use App\Models\Customer;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Lunar\Models\Order;

#[Layout('layouts.storefront')]
class CustomerOrderDetailsPage extends Component
{
    public string $reference;

    public function mount(string $reference): void
    {
        $this->reference = $reference;
    }

    public function getOrderProperty(): Order
    {
        /** @var Customer|null $customer */
        $customer = Auth::guard('customer')->user();
        if (! $customer) {
            abort(403);
        }

        /** @var Order|null $order */
        $order = Order::query()
            ->where('reference', $this->reference)
            ->with(['lines.purchasable', 'shippingAddress'])
            ->first();

        if (! $order) {
            abort(404, 'الطلب غير موجود.');
        }

        // Authorize access
        $customerId = $order->getAttribute('storefront_customer_id');
        $phoneHash = $order->getAttribute('customer_phone_hash');

        $isOwner = ($customerId === $customer->id)
            || ($customer->phone_hash && $phoneHash === $customer->phone_hash);

        if (! $isOwner) {
            abort(403, 'غير مصرح لك بعرض هذا الطلب.');
        }

        return $order;
    }

    public function render(): View
    {
        return view('livewire.storefront.customer-order-details-page', [
            'order' => $this->getOrderProperty(),
            'pageTitle' => 'تفاصيل الطلب '.$this->reference,
        ]);
    }
}
