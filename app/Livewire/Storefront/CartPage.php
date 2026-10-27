<?php

declare(strict_types=1);

namespace App\Livewire\Storefront;

use App\Domain\Checkout\Services\StorefrontCartService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

class CartPage extends Component
{
    #[On('cart-updated')]
    public function refreshCart(): void
    {
        // Re-renders view
    }

    public function increment(int $variantId): void
    {
        $cart = app(StorefrontCartService::class);
        $raw = $cart->getRawCart();
        $current = $raw[$variantId] ?? 1;
        $cart->updateQuantity($variantId, $current + 1);
        $this->dispatch('cart-updated');
    }

    public function decrement(int $variantId): void
    {
        $cart = app(StorefrontCartService::class);
        $raw = $cart->getRawCart();
        $current = $raw[$variantId] ?? 1;
        if ($current > 1) {
            $cart->updateQuantity($variantId, $current - 1);
        } else {
            $cart->removeItem($variantId);
        }
        $this->dispatch('cart-updated');
    }

    public function removeItem(int $variantId): void
    {
        app(StorefrontCartService::class)->removeItem($variantId);
        $this->dispatch('cart-updated');
    }

    public function clearCart(): void
    {
        app(StorefrontCartService::class)->clear();
        $this->dispatch('cart-updated');
    }

    public function render(): View
    {
        $cartService = app(StorefrontCartService::class);
        $items = $cartService->getItems();
        $count = $cartService->getCount();
        $subtotalMinor = $cartService->getSubtotalMinor();

        return view('livewire.storefront.cart-page', [
            'items' => $items,
            'count' => $count,
            'subtotalMinor' => $subtotalMinor,
            'subtotalDecimal' => number_format($subtotalMinor / 100, 2),
        ])->layout('layouts.storefront', [
            'title' => 'سلة المشتريات',
        ]);
    }
}
