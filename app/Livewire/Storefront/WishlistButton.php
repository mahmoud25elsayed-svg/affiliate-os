<?php

declare(strict_types=1);

namespace App\Livewire\Storefront;

use App\Models\Customer;
use App\Models\CustomerWishlist;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class WishlistButton extends Component
{
    public int $productId;

    public bool $isWishlisted = false;

    public function mount(int $productId): void
    {
        $this->productId = $productId;
        $this->checkStatus();
    }

    protected function checkStatus(): void
    {
        /** @var Customer|null $customer */
        $customer = Auth::guard('customer')->user();
        if ($customer) {
            $this->isWishlisted = CustomerWishlist::query()
                ->where('customer_id', $customer->id)
                ->where('product_id', $this->productId)
                ->exists();
        } else {
            $this->isWishlisted = false;
        }
    }

    public function toggle(): mixed
    {
        /** @var Customer|null $customer */
        $customer = Auth::guard('customer')->user();
        if (! $customer) {
            return redirect()->route('storefront.login');
        }

        $tenantId = function_exists('tenancy') && tenancy()->initialized ? (string) tenant('id') : 'default';

        $existing = CustomerWishlist::query()
            ->where('customer_id', $customer->id)
            ->where('product_id', $this->productId)
            ->first();

        if ($existing) {
            $existing->delete();
            $this->isWishlisted = false;
        } else {
            CustomerWishlist::create([
                'tenant_id' => $tenantId,
                'customer_id' => $customer->id,
                'product_id' => $this->productId,
            ]);
            $this->isWishlisted = true;
        }

        return null;
    }

    public function render(): View
    {
        return view('livewire.storefront.wishlist-button');
    }
}
