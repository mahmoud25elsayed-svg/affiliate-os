<?php

declare(strict_types=1);

namespace App\Domain\Affiliate\Actions;

use App\Domain\Affiliate\DTOs\PriceQuoteResult;
use App\Domain\Affiliate\Settings\AffiliateProgramSettings;
use App\Domain\Checkout\Services\ShippingCalculatorService;
use App\Domain\Orders\Enums\OrderStatus;
use App\Models\WholesalePrice;
use App\Support\Settings\ShippingSettings;
use DomainException;
use Lunar\Models\Order;
use Lunar\Models\Product;
use Lunar\Models\ProductVariant;

class PriceQuoteAction
{
    public function __construct(
        protected ShippingCalculatorService $shippingCalculator
    ) {}

    /**
     * Calculate server-side price quote for an affiliate direct order item.
     *
     * @throws DomainException
     */
    public function execute(
        ProductVariant $variant,
        int $quantity,
        int $sellingPriceMinor,
        int $governorateId
    ): PriceQuoteResult {
        if ($quantity <= 0) {
            throw new DomainException('يجب أن تكون الكمية المطلوبة قطعة واحدة على الأقل.');
        }

        /** @var WholesalePrice|null $wholesalePrice */
        $wholesalePrice = $variant->wholesalePrice ?? WholesalePrice::query()->where('product_variant_id', $variant->id)->first();
        if (! $wholesalePrice instanceof WholesalePrice || ! $wholesalePrice->isActive()) {
            throw new DomainException('هذا المنتج غير متاح للطلب بسعر الجملة حالياً.');
        }

        $wholesaleUnitMinor = (int) $wholesalePrice->wholesale_price_minor;
        $variantMinPriceMinor = (int) ($wholesalePrice->min_price_minor ?? $wholesaleUnitMinor);
        $variantMaxPriceMinor = (int) ($wholesalePrice->max_price_minor ?? $variantMinPriceMinor);

        // 1. Combine Product Bounds with Merchant Affiliate Program Settings
        $programSettings = AffiliateProgramSettings::load();

        $effectiveMinUnitMinor = max(
            $variantMinPriceMinor,
            $wholesaleUnitMinor + $programSettings->min_selling_markup_minor
        );

        $effectiveMaxUnitMinor = min(
            $variantMaxPriceMinor,
            $wholesaleUnitMinor + $programSettings->max_selling_markup_minor
        );

        if ($effectiveMaxUnitMinor < $effectiveMinUnitMinor) {
            $effectiveMaxUnitMinor = $effectiveMinUnitMinor;
        }

        // 2. Strict Range Enforcement
        if ($sellingPriceMinor < $effectiveMinUnitMinor || $sellingPriceMinor > $effectiveMaxUnitMinor) {
            $sellingFormatted = number_format($sellingPriceMinor / 100, 2);
            $minFormatted = number_format($effectiveMinUnitMinor / 100, 2);
            $maxFormatted = number_format($effectiveMaxUnitMinor / 100, 2);

            throw new DomainException(
                "سعر البيع المطلوب ({$sellingFormatted} ج.م) خارج النطاق المسموح به لهذا المنتج. الحد الأدنى: {$minFormatted} ج.م، والحد الأقصى: {$maxFormatted} ج.م."
            );
        }

        // 3. Subtotal & Profit
        $subtotalMinor = $sellingPriceMinor * $quantity;
        $affiliateUnitProfitMinor = $sellingPriceMinor - $wholesaleUnitMinor;
        $affiliateProfitMinor = $affiliateUnitProfitMinor * $quantity;

        // 4. Server-Side Shipping & COD Fee
        $shippingQuote = $this->shippingCalculator->calculate($governorateId, $subtotalMinor);
        $shippingCostMinor = $shippingQuote->rate_minor;

        $shippingSettings = ShippingSettings::load();
        $codFeeMinor = $shippingSettings->allow_cod ? $shippingSettings->cod_fee_minor : 0;

        $customerTotalMinor = $subtotalMinor + $shippingCostMinor + $codFeeMinor;

        // 5. Smart Warnings
        $warnings = [];

        // Warning: Low margin (< 10% markup over wholesale)
        if ($wholesaleUnitMinor > 0 && ($affiliateUnitProfitMinor / $wholesaleUnitMinor) < 0.10) {
            $warnings[] = 'WARNING_LOW_MARGIN: هامش الربح المحدد منخفض جداً وقريب من سعر التكلفة.';
        }

        // Warning: Low stock
        $stock = (int) ($variant->stock ?? 0);
        if ($stock > 0 && $stock <= 5) {
            $warnings[] = "WARNING_LOW_STOCK: المخزون المتبقي لهذا المنتج منخفض جداً ({$stock} قطع فقط).";
        }

        // Warning: High return rate in governorate
        $highReturn = $this->isGovernorateHighRisk($governorateId);
        if ($highReturn) {
            $warnings[] = 'WARNING_HIGH_RETURN_REGION: نسبة الارتجاع في هذه المحافظة مرتفعة تاريخياً.';
        }

        /** @var Product|null $product */
        $product = $variant->product;
        $productName = $product instanceof Product ? ($product->translateAttribute('name') ?? 'منتج') : 'منتج';
        $sku = $variant->sku ?? 'SKU-'.$variant->id;

        return new PriceQuoteResult(
            variantId: (int) $variant->id,
            sku: $sku,
            productName: $productName,
            quantity: $quantity,
            wholesaleUnitPriceMinor: $wholesaleUnitMinor,
            minAllowedUnitPriceMinor: $effectiveMinUnitMinor,
            maxAllowedUnitPriceMinor: $effectiveMaxUnitMinor,
            sellingUnitPriceMinor: $sellingPriceMinor,
            subtotalMinor: $subtotalMinor,
            shippingCostMinor: $shippingCostMinor,
            codFeeMinor: $codFeeMinor,
            affiliateProfitMinor: $affiliateProfitMinor,
            customerTotalMinor: $customerTotalMinor,
            governorateId: $governorateId,
            warnings: $warnings
        );
    }

    protected function isGovernorateHighRisk(int $governorateId): bool
    {
        $tenantId = function_exists('tenancy') && tenancy()->initialized ? (string) tenant('id') : null;
        if (! $tenantId) {
            return false;
        }

        $ordersQuery = Order::query()
            ->where('tenant_id', $tenantId)
            ->where('governorate_id', $governorateId);

        $total = (clone $ordersQuery)->count();
        if ($total < 10) {
            return false;
        }

        $returns = (clone $ordersQuery)
            ->whereIn('status', [OrderStatus::RETURNED->value, OrderStatus::DELIVERY_FAILED->value])
            ->count();

        return ($returns / $total) > 0.25;
    }
}
