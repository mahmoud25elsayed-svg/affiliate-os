<?php

declare(strict_types=1);

namespace App\Domain\Checkout\Services;

use Illuminate\Support\Facades\Session;
use Lunar\Models\Price;
use Lunar\Models\Product;
use Lunar\Models\ProductVariant;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class StorefrontCartService
{
    protected function getSessionKey(): string
    {
        $tenantId = function_exists('tenancy') && tenancy()->initialized ? (string) tenant('id') : 'default';

        return "storefront_cart_{$tenantId}";
    }

    /**
     * @return array<int, int> [variant_id => quantity]
     */
    public function getRawCart(): array
    {
        /** @var array<int, int> $cart */
        $cart = Session::get($this->getSessionKey(), []);

        return $cart;
    }

    /**
     * @param  array<int, int>  $cart
     */
    protected function saveRawCart(array $cart): void
    {
        Session::put($this->getSessionKey(), $cart);
    }

    public function addItem(int $variantId, int $quantity = 1): void
    {
        $cart = $this->getRawCart();

        if (isset($cart[$variantId])) {
            $cart[$variantId] += max(1, $quantity);
        } else {
            $cart[$variantId] = max(1, $quantity);
        }

        $this->saveRawCart($cart);
    }

    public function updateQuantity(int $variantId, int $quantity): void
    {
        $cart = $this->getRawCart();

        if ($quantity <= 0) {
            unset($cart[$variantId]);
        } else {
            $cart[$variantId] = $quantity;
        }

        $this->saveRawCart($cart);
    }

    public function removeItem(int $variantId): void
    {
        $cart = $this->getRawCart();
        unset($cart[$variantId]);
        $this->saveRawCart($cart);
    }

    public function clear(): void
    {
        Session::forget($this->getSessionKey());
    }

    public function getCount(): int
    {
        return array_sum($this->getRawCart());
    }

    /**
     * @return list<array{
     *     variant_id: int,
     *     variant: ProductVariant,
     *     name: string,
     *     sku: string,
     *     quantity: int,
     *     unit_price_minor: int,
     *     subtotal_minor: int,
     *     image_url: string|null
     * }>
     */
    public function getItems(): array
    {
        $raw = $this->getRawCart();

        if (empty($raw)) {
            return [];
        }

        $variantIds = array_keys($raw);
        $variants = ProductVariant::query()
            ->with(['product.media', 'prices'])
            ->whereIn('id', $variantIds)
            ->get()
            ->keyBy('id');

        $items = [];

        foreach ($raw as $variantId => $quantity) {
            /** @var ProductVariant|null $variant */
            $variant = $variants->get($variantId);

            if ($variant === null) {
                // If variant no longer exists, prune it
                $this->removeItem($variantId);

                continue;
            }

            /** @var Product|null $product */
            $product = $variant->product;
            $name = $product instanceof Product ? ($product->translateAttribute('name') ?? 'منتج') : 'منتج';
            $sku = $variant->sku ?? 'SKU-'.$variant->id;

            /** @var Price|null $price */
            $price = $variant->prices->first();
            $unitPriceMinor = $price instanceof Price ? $price->price->value : 0;
            $subtotalMinor = $unitPriceMinor * $quantity;

            /** @var Media|null $thumbnail */
            $thumbnail = $product instanceof Product ? $product->media->first() : null;
            $imageUrl = $thumbnail instanceof Media ? $thumbnail->getUrl() : null;

            $items[] = [
                'variant_id' => $variant->id,
                'variant' => $variant,
                'name' => $name,
                'sku' => $sku,
                'quantity' => $quantity,
                'unit_price_minor' => $unitPriceMinor,
                'subtotal_minor' => $subtotalMinor,
                'image_url' => $imageUrl,
            ];
        }

        return $items;
    }

    public function getSubtotalMinor(): int
    {
        $items = $this->getItems();
        $subtotal = 0;
        foreach ($items as $item) {
            $subtotal += $item['subtotal_minor'];
        }

        return $subtotal;
    }

    /**
     * Merge guest cart into user cart.
     *
     * @param  array<int, int>  $guestItems
     */
    public function merge(array $guestItems): void
    {
        $current = $this->getRawCart();

        foreach ($guestItems as $variantId => $quantity) {
            if (isset($current[$variantId])) {
                $current[$variantId] += $quantity;
            } else {
                $current[$variantId] = $quantity;
            }
        }

        $this->saveRawCart($current);
    }
}
