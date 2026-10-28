<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Affiliate;

use App\Domain\Affiliate\Settings\AffiliateProgramSettings;
use App\Http\Controllers\Controller;
use App\Models\WholesalePrice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Lunar\Models\Price;
use Lunar\Models\Product;
use Lunar\Models\ProductVariant;

class AffiliateCatalogApiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = min(max((int) $request->input('per_page', 20), 1), 100);
        $settings = AffiliateProgramSettings::load();

        $query = ProductVariant::query()
            ->whereHas('wholesalePrice', fn ($q) => $q->where('status', 'active'))
            ->with(['product.media', 'wholesalePrice', 'prices'])
            ->latest();

        $variants = $query->paginate($perPage);

        /** @var array<ProductVariant> $variantItems */
        $variantItems = $variants->items();

        $items = array_map(function (ProductVariant $variant) use ($settings): array {
            /** @var WholesalePrice|null $wholesale */
            $wholesale = $variant->getRelationValue('wholesalePrice');
            /** @var Product|null $product */
            $product = $variant->product instanceof Product ? $variant->product : null;
            /** @var Price|null $retailPrice */
            $retailPrice = $variant->prices->first();
            $retailMinor = $retailPrice ? (int) $retailPrice->price->value : 0;

            $suggestedRetail = $wholesale
                ? ($wholesale->compare_at_price_minor ?? $wholesale->min_price_minor ?? $retailMinor)
                : $retailMinor;

            $minCommission = $wholesale && $wholesale->min_commission_minor !== null
                ? $wholesale->min_commission_minor
                : $settings->min_commission_minor;

            $maxCommission = $wholesale && $wholesale->max_commission_minor !== null
                ? $wholesale->max_commission_minor
                : $settings->max_commission_minor;

            return [
                'variant_id' => $variant->id,
                'product_id' => $variant->product_id,
                'product_name' => $product ? (string) $product->translateAttribute('name') : $variant->sku,
                'sku' => $variant->sku,
                'stock' => $variant->stock,
                'wholesale_price_minor' => $wholesale?->wholesale_price_minor,
                'suggested_retail_price_minor' => $suggestedRetail,
                'min_commission_minor' => $minCommission,
                'max_commission_minor' => $maxCommission,
                'min_selling_markup_minor' => $settings->min_selling_markup_minor,
                'max_selling_markup_minor' => $settings->max_selling_markup_minor,
            ];
        }, $variantItems);

        return response()->json([
            'data' => $items,
            'meta' => [
                'current_page' => $variants->currentPage(),
                'last_page' => $variants->lastPage(),
                'per_page' => $variants->perPage(),
                'total' => $variants->total(),
            ],
        ]);
    }

    public function show(int $variantId): JsonResponse
    {
        $settings = AffiliateProgramSettings::load();

        /** @var ProductVariant $variant */
        $variant = ProductVariant::whereHas('wholesalePrice', fn ($q) => $q->where('status', 'active'))
            ->with(['product.media', 'wholesalePrice', 'prices'])
            ->findOrFail($variantId);

        /** @var WholesalePrice|null $wholesale */
        $wholesale = $variant->getRelationValue('wholesalePrice');
        /** @var Product|null $product */
        $product = $variant->product instanceof Product ? $variant->product : null;
        /** @var Price|null $retailPrice */
        $retailPrice = $variant->prices->first();
        $retailMinor = $retailPrice ? (int) $retailPrice->price->value : 0;

        $suggestedRetail = $wholesale
            ? ($wholesale->compare_at_price_minor ?? $wholesale->min_price_minor ?? $retailMinor)
            : $retailMinor;

        $minCommission = $wholesale && $wholesale->min_commission_minor !== null
            ? $wholesale->min_commission_minor
            : $settings->min_commission_minor;

        $maxCommission = $wholesale && $wholesale->max_commission_minor !== null
            ? $wholesale->max_commission_minor
            : $settings->max_commission_minor;

        return response()->json([
            'data' => [
                'variant_id' => $variant->id,
                'product_id' => $variant->product_id,
                'product_name' => $product ? (string) $product->translateAttribute('name') : $variant->sku,
                'sku' => $variant->sku,
                'stock' => $variant->stock,
                'wholesale_price_minor' => $wholesale?->wholesale_price_minor,
                'suggested_retail_price_minor' => $suggestedRetail,
                'min_commission_minor' => $minCommission,
                'max_commission_minor' => $maxCommission,
            ],
        ]);
    }
}
