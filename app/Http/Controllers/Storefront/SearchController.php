<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Domain\Storefront\Services\ArabicSearchService;
use App\Http\Controllers\Controller;
use App\Support\Settings\GeneralSettings;
use App\Support\Settings\ThemeSettings;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function __invoke(Request $request, ArabicSearchService $searchService): View
    {
        $query = (string) $request->input('q', '');
        $theme = ThemeSettings::load();
        $general = GeneralSettings::load();

        $products = empty(trim($query))
            ? collect()
            : $searchService->search($query)
                ->with(['variants.prices', 'variants.wholesalePrice'])
                ->paginate(16)
                ->withQueryString();

        return view('storefront.search', [
            'query' => $query,
            'products' => $products,
            'theme' => $theme,
            'general' => $general,
        ]);
    }
}
