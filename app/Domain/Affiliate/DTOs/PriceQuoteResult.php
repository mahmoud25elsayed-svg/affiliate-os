<?php

declare(strict_types=1);

namespace App\Domain\Affiliate\DTOs;

readonly class PriceQuoteResult
{
    /**
     * @param  list<string>  $warnings
     */
    public function __construct(
        public int $variantId,
        public string $sku,
        public string $productName,
        public int $quantity,
        public int $wholesaleUnitPriceMinor,
        public int $minAllowedUnitPriceMinor,
        public int $maxAllowedUnitPriceMinor,
        public int $sellingUnitPriceMinor,
        public int $subtotalMinor,
        public int $shippingCostMinor,
        public int $codFeeMinor,
        public int $affiliateProfitMinor,
        public int $customerTotalMinor,
        public int $governorateId,
        public array $warnings = []
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'variant_id' => $this->variantId,
            'sku' => $this->sku,
            'product_name' => $this->productName,
            'quantity' => $this->quantity,
            'wholesale_unit_price_minor' => $this->wholesaleUnitPriceMinor,
            'min_allowed_unit_price_minor' => $this->minAllowedUnitPriceMinor,
            'max_allowed_unit_price_minor' => $this->maxAllowedUnitPriceMinor,
            'selling_unit_price_minor' => $this->sellingUnitPriceMinor,
            'subtotal_minor' => $this->subtotalMinor,
            'shipping_cost_minor' => $this->shippingCostMinor,
            'cod_fee_minor' => $this->codFeeMinor,
            'affiliate_profit_minor' => $this->affiliateProfitMinor,
            'customer_total_minor' => $this->customerTotalMinor,
            'governorate_id' => $this->governorateId,
            'warnings' => $this->warnings,
        ];
    }
}
