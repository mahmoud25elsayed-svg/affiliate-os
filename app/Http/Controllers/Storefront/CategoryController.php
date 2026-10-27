<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Domain\Storefront\Services\SeoSchemaService;
use App\Http\Controllers\Controller;
use App\Support\Settings\GeneralSettings;
use App\Support\Settings\ThemeSettings;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Lunar\Models\Collection;

class CategoryController extends Controller
{
    public function index(Request $request, SeoSchemaService $seoService): View
    {
        $theme = ThemeSettings::load();
        $general = GeneralSettings::load();

        $breadcrumbs = [
            ['name' => 'الرئيسية', 'url' => url('/')],
            ['name' => 'كافة المنتجات', 'url' => url('/products')],
        ];

        $breadcrumbSchema = $seoService->breadcrumbSchema($breadcrumbs);

        return view('storefront.category', [
            'category' => null,
            'theme' => $theme,
            'general' => $general,
            'breadcrumbSchema' => $breadcrumbSchema,
            'breadcrumbs' => $breadcrumbs,
        ]);
    }

    public function show(Request $request, int|string $id, SeoSchemaService $seoService): View
    {
        /** @var Collection $category */
        $category = Collection::findOrFail($id);
        $theme = ThemeSettings::load();
        $general = GeneralSettings::load();

        $breadcrumbs = [
            ['name' => 'الرئيسية', 'url' => url('/')],
            ['name' => (string) ($category->translateAttribute('name') ?? 'فئة'), 'url' => url('/categories/'.$category->id)],
        ];

        $breadcrumbSchema = $seoService->breadcrumbSchema($breadcrumbs);

        return view('storefront.category', [
            'category' => $category,
            'theme' => $theme,
            'general' => $general,
            'breadcrumbSchema' => $breadcrumbSchema,
            'breadcrumbs' => $breadcrumbs,
        ]);
    }
}
