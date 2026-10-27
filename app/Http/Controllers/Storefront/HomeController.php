<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Domain\Storefront\Services\SeoSchemaService;
use App\Domain\Storefront\Services\StorefrontCacheService;
use App\Http\Controllers\Controller;
use App\Support\Settings\GeneralSettings;
use App\Support\Settings\ThemeSettings;
use Illuminate\Contracts\View\View;
use Lunar\Models\Collection;
use Lunar\Models\Product;

class HomeController extends Controller
{
    public function __invoke(SeoSchemaService $seoService): View
    {
        $theme = ThemeSettings::load();
        $general = GeneralSettings::load();

        $data = StorefrontCacheService::remember('home_data', 3600, function () {
            $categories = Collection::withCount('products')
                ->whereNull('parent_id')
                ->take(8)
                ->get();

            $featuredProducts = Product::with(['variants.prices', 'variants.wholesalePrice', 'media'])
                ->where('status', 'published')
                ->latest()
                ->take(8)
                ->get();

            $latestProducts = Product::with(['variants.prices', 'variants.wholesalePrice', 'media'])
                ->where('status', 'published')
                ->latest()
                ->skip(8)
                ->take(8)
                ->get();

            if ($latestProducts->isEmpty()) {
                $latestProducts = $featuredProducts;
            }

            return [
                'categories' => $categories,
                'featuredProducts' => $featuredProducts,
                'latestProducts' => $latestProducts,
            ];
        });

        $orgSchema = $seoService->organizationSchema();

        return view('storefront.home', [
            'theme' => $theme,
            'general' => $general,
            'categories' => $data['categories'],
            'featuredProducts' => $data['featuredProducts'],
            'latestProducts' => $data['latestProducts'],
            'orgSchema' => $orgSchema,
        ]);
    }
}
