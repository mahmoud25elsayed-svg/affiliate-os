<?php

declare(strict_types=1);

namespace App\Domain\Promotions\Services;

use App\Domain\Promotions\Events\FlashSaleStockDepleted;
use App\Models\Coupon;
use App\Models\DailyDeal;
use App\Models\FlashSaleItem;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use Lunar\Models\Order;
use Lunar\Models\OrderLine;
use Lunar\Models\Price;
use Lunar\Models\ProductVariant;

class PromotionPricingService
{
    /**
     * Resolve effective selling price for a product variant taking into account
     * active flash sales, daily deals, or returning the standard price.
     *
     * @return array{
     *     price_minor: int,
     *     original_price_minor: int,
     *     is_promotional: bool,
     *     promotion_type: string|null,
     *     flash_sale_item: FlashSaleItem|null,
     *     daily_deal: DailyDeal|null
     * }
     */
    public function getEffectiveVariantPrice(ProductVariant $variant): array
    {
        /** @var Price|null $firstPrice */
        $firstPrice = $variant->prices->first();
        /** @var mixed $rawPrice */
        $rawPrice = $firstPrice instanceof Price ? $firstPrice->price : null;
        $standardPriceMinor = is_object($rawPrice) && isset($rawPrice->value)
            ? (int) $rawPrice->value
            : (int) ($rawPrice ?? 0);

        // 1. Check for active Flash Sale with available quota
        $flashItem = FlashSaleItem::query()
            ->where('product_variant_id', $variant->id)
            ->whereHas('flashSale', function ($q) {
                $now = now();
                $q->where('is_active', true)
                    ->where('starts_at', '<=', $now)
                    ->where('ends_at', '>', $now);
            })
            ->first();

        if ($flashItem && $flashItem->isAvailable()) {
            return [
                'price_minor' => $flashItem->flash_price_minor,
                'original_price_minor' => $standardPriceMinor,
                'is_promotional' => true,
                'promotion_type' => 'flash_sale',
                'flash_sale_item' => $flashItem,
                'daily_deal' => null,
            ];
        }

        // 2. Check for today's Daily Deal
        $dailyDeal = DailyDeal::query()
            ->where(function ($q) use ($variant) {
                $q->where('product_variant_id', $variant->id)
                    ->orWhere(function ($sub) use ($variant) {
                        $sub->whereNull('product_variant_id')
                            ->where('product_id', $variant->product_id);
                    });
            })
            ->where('is_active', true)
            ->whereDate('deal_date', Carbon::today())
            ->first();

        if ($dailyDeal) {
            $dealPriceMinor = $dailyDeal->calculateDiscountedPrice($standardPriceMinor);

            return [
                'price_minor' => $dealPriceMinor,
                'original_price_minor' => $standardPriceMinor,
                'is_promotional' => true,
                'promotion_type' => 'daily_deal',
                'flash_sale_item' => null,
                'daily_deal' => $dailyDeal,
            ];
        }

        // 3. Standard non-promotional price
        return [
            'price_minor' => $standardPriceMinor,
            'original_price_minor' => $standardPriceMinor,
            'is_promotional' => false,
            'promotion_type' => null,
            'flash_sale_item' => null,
            'daily_deal' => null,
        ];
    }

    /**
     * Calculate coupon-eligible subtotal enforcing non-stacking rules:
     * Flash sale items and bundle free items cannot be discounted by percentage/fixed coupons.
     *
     * @param  array<int, array{variant: ProductVariant, quantity: int, unit_price_minor: int, is_flash_sale?: bool}>  $items
     */
    public function getCouponEligibleSubtotal(array $items, Coupon $coupon): int
    {
        // Free shipping coupon is calculated against the whole cart order total
        if ($coupon->type === 'free_shipping') {
            $total = 0;
            foreach ($items as $item) {
                $total += $item['unit_price_minor'] * $item['quantity'];
            }

            return $total;
        }

        // For percentage/fixed coupons: exclude flash sale items
        $eligibleSubtotal = 0;
        $hasFlashItems = false;

        foreach ($items as $item) {
            $pricing = $this->getEffectiveVariantPrice($item['variant']);
            if ($pricing['is_promotional'] && $pricing['promotion_type'] === 'flash_sale') {
                $hasFlashItems = true;

                continue; // Flash items are NOT eligible for coupon discount
            }

            $eligibleSubtotal += $item['unit_price_minor'] * $item['quantity'];
        }

        if ($eligibleSubtotal === 0 && $hasFlashItems) {
            throw ValidationException::withMessages([
                'coupon_code' => __('هذا الكوبون لا يسري على المنتجات المشمولة بعروض التخفيض الفوري (الفلاش سيل).'),
            ]);
        }

        return $eligibleSubtotal;
    }

    /**
     * Record flash sale allocations when an order is created.
     */
    public function recordOrderPromotions(Order $order): void
    {
        /** @var Collection<int, OrderLine> $lines */
        $lines = $order->lines;

        foreach ($lines as $line) {
            if ($line->purchasable_type !== ProductVariant::class || ! $line->purchasable_id) {
                continue;
            }

            $flashItem = FlashSaleItem::query()
                ->where('product_variant_id', $line->purchasable_id)
                ->whereHas('flashSale', function ($q) {
                    $now = now();
                    $q->where('is_active', true)
                        ->where('starts_at', '<=', $now)
                        ->where('ends_at', '>', $now);
                })
                ->first();

            if ($flashItem) {
                $flashItem->recordSale((int) $line->quantity);

                if (! $flashItem->isAvailable()) {
                    Event::dispatch(new FlashSaleStockDepleted($flashItem));
                }
            }
        }
    }
}
