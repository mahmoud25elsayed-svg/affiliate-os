<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Services;

use App\Models\Tenant;
use App\Support\Billing\Limits;
use Lunar\FieldTypes\Text;
use Lunar\Models\Product;
use Lunar\Models\ProductType;

class ProductService
{
    /**
     * @param  array{name?: string, status?: string, product_type_id?: int}  $data
     */
    public function createProduct(array $data, ?Tenant $tenant = null): Product
    {
        // Enforce product limit at the service level!
        Limits::ensure('products', $tenant);

        $productType = isset($data['product_type_id'])
            ? ProductType::findOrFail($data['product_type_id'])
            : (ProductType::first() ?? ProductType::create(['name' => 'Standard']));

        /** @var Product $product */
        $product = Product::create([
            'product_type_id' => $productType->id,
            'status' => $data['status'] ?? 'published',
            'attribute_data' => [
                'name' => new Text($data['name'] ?? 'Product '.uniqid()),
            ],
        ]);

        return $product;
    }
}
