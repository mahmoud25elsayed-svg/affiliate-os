<?php

declare(strict_types=1);

namespace App\Domain\Affiliate\Services;

use App\Domain\Affiliate\Settings\AffiliateProgramSettings;
use App\Models\WholesalePrice;
use App\Support\Money;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Lunar\Models\ProductVariant;

/**
 * Validates an affiliate's custom selling price for a tracking link (web portal and PWA share this).
 */
class TrackingLinkPriceValidator
{
    /** Validation rule for the raw "custom_selling_price" input: plain decimal, at most 2 fraction digits. */
    public const INPUT_RULE = ['nullable', 'regex:/^\d{1,7}(\.\d{1,2})?$/'];

    /**
     * @return int Selling price in minor units (piasters)
     *
     * @throws ValidationException
     */
    public function resolveMinor(string $rawPrice, ?ProductVariant $variant, AffiliateProgramSettings $settings): int
    {
        try {
            $sellingPriceMinor = Money::fromDecimalString($rawPrice)->amount;
        } catch (InvalidArgumentException) {
            throw ValidationException::withMessages([
                'custom_selling_price' => [__('سعر البيع غير صالح.')],
            ]);
        }

        $wholesalePrice = $variant?->relationLoaded('wholesalePrice')
            ? $variant->getRelation('wholesalePrice')
            : ($variant ? WholesalePrice::query()->where('product_variant_id', $variant->id)->first() : null);

        if (! $wholesalePrice instanceof WholesalePrice) {
            throw ValidationException::withMessages([
                'product_id' => [__('هذا المنتج لا يحتوي على سعر جملة معتمد حالياً.')],
            ]);
        }

        $minAllowed = (int) ($wholesalePrice->min_selling_price_minor ?? ($wholesalePrice->wholesale_price_minor + $settings->min_selling_markup_minor));
        $maxAllowed = (int) ($wholesalePrice->max_selling_price_minor ?? ($wholesalePrice->wholesale_price_minor + $settings->max_selling_markup_minor));

        if ($sellingPriceMinor < $minAllowed || $sellingPriceMinor > $maxAllowed) {
            $minFormatted = Money::fromMinor($minAllowed)->toDecimalString();
            $maxFormatted = Money::fromMinor($maxAllowed)->toDecimalString();

            throw ValidationException::withMessages([
                'custom_selling_price' => ["سعر البيع يجب أن يكون بين {$minFormatted} و {$maxFormatted} ج.م"],
            ]);
        }

        return $sellingPriceMinor;
    }
}
