<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Store;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Lunar\FieldTypes\Text;
use Lunar\Models\Currency;
use Lunar\Models\Price;
use Lunar\Models\Product;
use Lunar\Models\ProductType;
use Lunar\Models\ProductVariant;
use Lunar\Models\TaxClass;

class StoreProductApiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = min(max((int) $request->input('per_page', 20), 1), 100);

        $query = Product::query()
            ->with(['variants.prices', 'variants.wholesalePrice', 'media'])
            ->latest();

        if ($request->has('status')) {
            $query->where('status', (string) $request->input('status'));
        }

        $products = $query->paginate($perPage);

        return response()->json([
            'data' => $products->items(),
            'meta' => [
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'per_page' => $products->perPage(),
                'total' => $products->total(),
            ],
        ]);
    }

    public function show(int $id): JsonResponse
    {
        /** @var Product $product */
        $product = Product::with(['variants.prices', 'variants.wholesalePrice', 'media'])
            ->findOrFail($id);

        return response()->json([
            'data' => $product,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'sku' => ['required', 'string', 'max:100'],
            'price_minor' => ['required', 'integer', 'min:100'],
            'stock' => ['nullable', 'integer', 'min:0'],
            'status' => ['nullable', 'string', 'in:draft,published'],
        ]);

        $productType = ProductType::firstOrCreate(
            ['name' => 'Standard Product']
        );

        $taxClass = TaxClass::firstOrCreate(
            ['name' => 'Default Tax']
        );

        $currency = Currency::where('default', true)->first()
            ?? Currency::firstOrCreate(['code' => 'EGP'], ['name' => 'Egyptian Pound', 'exchange_rate' => 1.0, 'decimal_places' => 2, 'enabled' => true, 'default' => true]);

        /** @var Product $product */
        $product = Product::create([
            'product_type_id' => $productType->id,
            'status' => $validated['status'] ?? 'published',
            'attribute_data' => [
                'name' => new Text($validated['name']),
            ],
        ]);

        /** @var ProductVariant $variant */
        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'tax_class_id' => $taxClass->id,
            'sku' => $validated['sku'],
            'stock' => $validated['stock'] ?? 100,
        ]);

        Price::create([
            'priceable_type' => ProductVariant::class,
            'priceable_id' => $variant->id,
            'currency_id' => $currency->id,
            'price' => (int) $validated['price_minor'],
        ]);

        $product->load(['variants.prices']);

        return response()->json([
            'message' => 'Product created successfully.',
            'data' => $product,
        ], 201);
    }
}
