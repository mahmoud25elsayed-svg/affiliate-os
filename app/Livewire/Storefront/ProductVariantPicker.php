<?php

declare(strict_types=1);

namespace App\Livewire\Storefront;

use App\Domain\Checkout\Services\StorefrontCartService;
use App\Domain\Promotions\Services\PromotionPricingService;
use App\Models\WholesalePrice;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Lunar\Models\Price;
use Lunar\Models\Product;
use Lunar\Models\ProductVariant;

class ProductVariantPicker extends Component
{
    public Product $product;

    public ?int $selectedVariantId = null;

    public int $quantity = 1;

    public function mount(Product $product): void
    {
        $this->product = $product;
        /** @var ProductVariant|null $firstVariant */
        $firstVariant = $product->variants->first();
        $this->selectedVariantId = $firstVariant?->id;
    }

    public function increment(): void
    {
        $this->quantity++;
    }

    public function decrement(): void
    {
        if ($this->quantity > 1) {
            $this->quantity--;
        }
    }

    public function addToCart(): void
    {
        if ($this->selectedVariantId !== null) {
            app(StorefrontCartService::class)->addItem(
                $this->selectedVariantId,
                $this->quantity
            );
        }

        $this->dispatch('cart-updated', [
            'product_id' => $this->product->id,
            'variant_id' => $this->selectedVariantId,
            'quantity' => $this->quantity,
        ]);

        $this->dispatch('notify', [
            'message' => 'تمت إضافة المنتج إلى السلة بنجاح!',
            'type' => 'success',
        ]);
    }

    public function buyNow(): void
    {
        $this->addToCart();
        $this->redirect(url('/checkout'));
    }

    public function render(): View
    {
        /** @var ProductVariant|null $selectedVariant */
        $selectedVariant = $this->product->variants->where('id', $this->selectedVariantId)->first()
            ?? $this->product->variants->first();

        /** @var Price|null $price */
        $price = $selectedVariant?->prices->first();
        /** @var WholesalePrice|null $pricing */
        $pricing = $selectedVariant ? $selectedVariant->getRelationValue('wholesalePrice') : null;

        $promotional = $selectedVariant
            ? app(PromotionPricingService::class)->getEffectiveVariantPrice($selectedVariant)
            : null;

        return view('livewire.storefront.product-variant-picker', [
            'selectedVariant' => $selectedVariant,
            'price' => $price,
            'pricing' => $pricing,
            'promotional' => $promotional,
        ]);
    }
}
