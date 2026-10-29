<?php

declare(strict_types=1);

namespace App\Domain\CallCenter\Services;

use App\Domain\Affiliate\Actions\PriceQuoteAction;
use App\Domain\Affiliate\DTOs\PriceQuoteResult;
use App\Models\OrderEvent;
use App\Models\User;
use App\Support\Location\City;
use App\Support\Location\Governorate;
use DomainException;
use Lunar\Models\Order;
use Lunar\Models\OrderAddress;
use Lunar\Models\OrderLine;
use Lunar\Models\ProductVariant;

class OrderModificationService
{
    public function __construct(
        protected PriceQuoteAction $priceQuoteAction
    ) {}

    /**
     * Modify an order's items or shipping details during a call and recalculate price quote and affiliate profit.
     *
     * @param  array{
     *     variant_id?: int,
     *     quantity?: int,
     *     selling_price_minor?: int,
     *     governorate_id?: int,
     *     city?: string,
     *     line_one?: string,
     *     contact_phone?: string,
     *     customer_name?: string
     * }  $data
     * @return array{
     *     order: Order,
     *     old_profit_minor: int,
     *     new_profit_minor: int,
     *     diff_profit_minor: int,
     *     quote: PriceQuoteResult
     * }
     */
    public function modifyOrder(Order $order, array $data, User $agent): array
    {
        $snapshot = is_array($order->price_snapshot)
            ? $order->price_snapshot
            : json_decode((string) $order->price_snapshot, true) ?? [];

        $oldProfitMinor = (int) ($snapshot['affiliate_profit_minor'] ?? 0);

        // 1. Resolve variant
        /** @var OrderLine|null $firstLine */
        $firstLine = $order->lines()->first();
        if (! empty($data['variant_id'])) {
            /** @var ProductVariant $variant */
            $variant = ProductVariant::query()->findOrFail((int) $data['variant_id']);
        } elseif ($firstLine && $firstLine->purchasable instanceof ProductVariant) {
            $variant = $firstLine->purchasable;
        } else {
            throw new DomainException('تعذر تحديد متغير المنتج للطلب.');
        }

        // 2. Resolve quantity and selling price (AF-04: preserve original line unit price, avoid hardcoded 20000 fallback)
        $firstLineQty = $firstLine instanceof OrderLine ? (int) $firstLine->quantity : 1;
        $quantity = isset($data['quantity']) ? (int) $data['quantity'] : (int) ($snapshot['quantity'] ?? $firstLineQty);
        if ($quantity <= 0) {
            throw new DomainException('الكمية يجب أن تكون قطعة واحدة على الأقل.');
        }

        $existingUnitPrice = $firstLine instanceof OrderLine ? (int) ($firstLine->unit_price->value ?? $firstLine->unit_price ?? 0) : 0;
        $fallbackSellingPrice = $existingUnitPrice > 0
            ? $existingUnitPrice
            : (int) ($variant->wholesalePrice?->min_price_minor ?? 20000);

        $sellingPriceMinor = isset($data['selling_price_minor'])
            ? (int) $data['selling_price_minor']
            : (int) ($snapshot['selling_price_minor'] ?? $fallbackSellingPrice);

        // 3. Resolve governorate
        $governorateId = isset($data['governorate_id'])
            ? (int) $data['governorate_id']
            : (int) ($snapshot['governorate_id'] ?? Governorate::query()->value('id') ?? 1);

        // 4. Calculate new price quote
        $quote = $this->priceQuoteAction->execute(
            variant: $variant,
            quantity: $quantity,
            sellingPriceMinor: $sellingPriceMinor,
            governorateId: $governorateId
        );

        $newProfitMinor = $quote->affiliateProfitMinor;
        $diffProfitMinor = $newProfitMinor - $oldProfitMinor;

        // 5. Update shipping address if provided
        /** @var OrderAddress|null $shippingAddress */
        $shippingAddress = $order->shippingAddress()->first();
        if ($shippingAddress instanceof OrderAddress) {
            $addrUpdates = [];
            if (! empty($data['customer_name'])) {
                $addrUpdates['first_name'] = $data['customer_name'];
            }
            if (! empty($data['contact_phone'])) {
                $addrUpdates['contact_phone'] = $data['contact_phone'];
            }
            if (! empty($data['line_one'])) {
                $addrUpdates['line_one'] = $data['line_one'];
            }
            if (! empty($data['city'])) {
                $addrUpdates['city'] = $data['city'];
            }
            if (! empty($addrUpdates)) {
                $shippingAddress->update($addrUpdates);
            }
        }

        // 6. Update Order lines & totals
        if ($firstLine instanceof OrderLine) {
            $firstLine->update([
                'purchasable_id' => $variant->id,
                'quantity' => $quantity,
                'unit_price' => $quote->sellingUnitPriceMinor,
                'sub_total' => $quote->subtotalMinor,
                'total' => $quote->subtotalMinor,
            ]);
        }

        $newSnapshot = array_merge($snapshot, [
            'quantity' => $quantity,
            'variant_id' => $variant->id,
            'variant_sku' => $variant->sku,
            'governorate_id' => $governorateId,
            'wholesale_unit_minor' => $quote->wholesaleUnitPriceMinor,
            'wholesale_total_minor' => $quote->wholesaleUnitPriceMinor * $quantity,
            'selling_price_minor' => $quote->sellingUnitPriceMinor,
            'selling_total_minor' => $quote->subtotalMinor,
            'shipping_cost_minor' => $quote->shippingCostMinor,
            'cod_fee_minor' => $quote->codFeeMinor,
            'customer_total_minor' => $quote->customerTotalMinor,
            'affiliate_profit_minor' => $newProfitMinor,
            'modified_during_call' => true,
        ]);

        $order->update([
            'sub_total' => $quote->subtotalMinor,
            'shipping_total' => $quote->shippingCostMinor,
            'total' => $quote->customerTotalMinor,
            'price_snapshot' => json_encode($newSnapshot),
        ]);

        // 7. Log modification in OrderEvent for full timeline transparency
        $diffSign = $diffProfitMinor >= 0 ? "+{$diffProfitMinor}" : (string) $diffProfitMinor;
        OrderEvent::create([
            'order_id' => $order->id,
            'from_status' => (string) $order->status,
            'to_status' => (string) $order->status,
            'actor_type' => User::class,
            'actor_id' => (string) $agent->id,
            'actor_name' => $agent->name,
            'note' => "تعديل الطلب أثناء مكالمة التأكيد: الكمية={$quantity}، المتغير={$variant->sku}. أرباح المسوق: {$oldProfitMinor} -> {$newProfitMinor} ({$diffSign} قرشاً)",
        ]);

        return [
            'order' => $order->fresh() ?? $order,
            'old_profit_minor' => $oldProfitMinor,
            'new_profit_minor' => $newProfitMinor,
            'diff_profit_minor' => $diffProfitMinor,
            'quote' => $quote,
        ];
    }
}
