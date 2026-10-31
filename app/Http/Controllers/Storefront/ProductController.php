<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Domain\Storefront\Services\SeoSchemaService;
use App\Http\Controllers\Controller;
use App\Models\WholesalePrice;
use App\Support\Settings\GeneralSettings;
use App\Support\Settings\ThemeSettings;
use Illuminate\Contracts\View\View;
use Lunar\Models\Collection;
use Lunar\Models\Product;
use Lunar\Models\ProductVariant;

class ProductController extends Controller
{
    public function show(int|string $id, SeoSchemaService $seoService): View
    {
        /** @var Product $product */
        $product = Product::with(['variants.prices', 'variants.wholesalePrice', 'collections', 'media', 'brand'])
            ->where('status', 'published')
            ->findOrFail($id);

        /** @var ProductVariant|null $firstVariant */
        $firstVariant = $product->variants->first();
        /** @var WholesalePrice|null $wholesalePrice */
        $wholesalePrice = $firstVariant ? $firstVariant->getRelationValue('wholesalePrice') : null;

        // If product is paused or archived in wholesale table, do not allow public browsing or show appropriate notice
        if ($wholesalePrice && $wholesalePrice->isArchived()) {
            abort(404, __('المنتج غير متوفر.'));
        }

        $theme = ThemeSettings::load();
        $general = GeneralSettings::load();

        $productSchema = $seoService->productSchema($product);

        /** @var Collection|null $collection */
        $collection = $product->collections->first();
        $breadcrumbs = [
            ['name' => __('الرئيسية'), 'url' => url('/')],
        ];

        if ($collection instanceof Collection) {
            $breadcrumbs[] = [
                'name' => (string) ($collection->translateAttribute('name') ?? __('فئة')),
                'url' => url('/categories/'.$collection->id),
            ];
        }

        $breadcrumbs[] = [
            'name' => (string) ($product->translateAttribute('name') ?? __('منتج')),
            'url' => url('/products/'.$product->id),
        ];

        $breadcrumbSchema = $seoService->breadcrumbSchema($breadcrumbs);

        // Related products in same category
        $collectionId = $collection instanceof Collection ? $collection->id : null;
        $relatedProducts = Product::with(['variants.prices', 'variants.wholesalePrice'])
            ->where('status', 'published')
            ->where('id', '!=', $product->id)
            ->when($collectionId !== null, function ($q) use ($collectionId) {
                $q->whereHas('collections', fn ($cq) => $cq->where('lunar_collections.id', $collectionId));
            })
            ->take(4)
            ->get();

        return view('storefront.product', [
            'product' => $product,
            'wholesalePrice' => $wholesalePrice,
            'relatedProducts' => $relatedProducts,
            'theme' => $theme,
            'general' => $general,
            'productSchema' => $productSchema,
            'breadcrumbSchema' => $breadcrumbSchema,
            'breadcrumbs' => $breadcrumbs,
        ]);
    }
}
