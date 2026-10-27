<?php

declare(strict_types=1);

namespace App\Livewire\Storefront;

use App\Domain\Checkout\Services\StorefrontCartService;
use App\Models\Customer;
use App\Models\CustomerWishlist;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Lunar\Models\ProductVariant;

#[Layout('layouts.storefront')]
class CustomerWishlistPage extends Component
{
    public ?string $successMessage = null;

    /**
     * @return Collection<int, CustomerWishlist>
     */
    public function getWishlistItemsProperty(): Collection
    {
        /** @var Customer|null $customer */
        $customer = Auth::guard('customer')->user();
        if (! $customer) {
            return new Collection;
        }

        return $customer->wishlists()->with(['product.media', 'product.variants.prices'])->latest('id')->get();
    }

    public function removeFromWishlist(int $wishlistId): void
    {
        /** @var Customer|null $customer */
        $customer = Auth::guard('customer')->user();
        if (! $customer) {
            abort(403);
        }

        CustomerWishlist::query()
            ->where('customer_id', $customer->id)
            ->where('id', $wishlistId)
            ->delete();

        $this->successMessage = 'تمت إزالة المنتج من المفضلة.';
    }

    public function addToCart(int $productId, StorefrontCartService $cartService): void
    {
        /** @var ProductVariant|null $variant */
        $variant = ProductVariant::query()->where('product_id', $productId)->first();
        if ($variant) {
            $cartService->addItem($variant->id, 1);
            $this->dispatch('cart-updated');
            $this->successMessage = 'تمت إضافة المنتج إلى السلة بنجاح.';
        }
    }

    public function render(): View
    {
        return view('livewire.storefront.customer-wishlist-page', [
            'items' => $this->getWishlistItemsProperty(),
            'pageTitle' => 'قائمة المفضلة',
        ]);
    }
}
