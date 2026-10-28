<?php

declare(strict_types=1);

namespace App\Http\Controllers\Affiliate;

use App\Domain\Affiliate\Settings\AffiliateProgramSettings;
use App\Http\Controllers\Controller;
use App\Models\WholesalePrice;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Lunar\Models\Collection;
use Lunar\Models\Product;

class AffiliateCatalogController extends Controller
{
    public function index(Request $request): View
    {
        $settings = AffiliateProgramSettings::load();

        $query = Product::query()
            ->where('status', 'published')
            ->with(['variants.wholesalePrice', 'thumbnail', 'collections']);

        // Filter by category
        if ($request->filled('collection_id')) {
            $collectionId = (int) $request->input('collection_id');
            $query->whereHas('collections', function ($q) use ($collectionId) {
                $q->where('lunar_collections.id', $collectionId);
            });
        }

        // Search by keyword
        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('attribute_data->name->value', 'like', "%{$search}%")
                    ->orWhereHas('variants', function ($vq) use ($search) {
                        $vq->where('sku', 'like', "%{$search}%");
                    });
            });
        }

        // Only include products that have at least one active wholesale price
        $query->whereHas('variants.wholesalePrice', function ($q) {
            $q->where('status', WholesalePrice::STATUS_ACTIVE);
        });

        $products = $query->paginate(12)->withQueryString();
        $collections = Collection::query()->get();

        return view('affiliate.catalog.index', [
            'products' => $products,
            'collections' => $collections,
            'settings' => $settings,
        ]);
    }
}
