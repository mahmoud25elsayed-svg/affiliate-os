<?php

namespace Tests\Feature;

use App\Models\Tenant;
use Lunar\FieldTypes\Text;
use Lunar\Models\Product;
use Lunar\Models\ProductType;
use Lunar\Models\ProductVariant;
use Lunar\Models\TaxClass;
use Tests\TestCase;

class TenantIsolationTest extends TestCase
{
    public function test_tenants_have_complete_data_isolation_for_lunar_products(): void
    {
        $tenant1 = $this->ensureTenantExists('tenant1');
        $tenant2 = $this->ensureTenantExists('tenant2');
        $tenant3 = $this->ensureTenantExists('tenant3');

        $this->assertNotNull($tenant1);
        $this->assertNotNull($tenant2);
        $this->assertNotNull($tenant3);

        // 1. Add Product to Tenant 1
        $tenant1->run(function () {
            ProductVariant::query()->delete();
            Product::query()->delete();
            $productType = ProductType::first();
            $taxClass = TaxClass::first();

            $p1 = Product::create([
                'product_type_id' => $productType->id,
                'status' => 'published',
                'attribute_data' => [
                    'name' => new Text('Product for Tenant 1'),
                ],
            ]);

            ProductVariant::create([
                'product_id' => $p1->id,
                'sku' => 'SKU-TENANT-1',
                'tax_class_id' => $taxClass->id,
            ]);
        });

        // 2. Add Product to Tenant 2
        $tenant2->run(function () {
            ProductVariant::query()->delete();
            Product::query()->delete();
            $productType = ProductType::first();
            $taxClass = TaxClass::first();

            $p2 = Product::create([
                'product_type_id' => $productType->id,
                'status' => 'published',
                'attribute_data' => [
                    'name' => new Text('Product for Tenant 2'),
                ],
            ]);

            ProductVariant::create([
                'product_id' => $p2->id,
                'sku' => 'SKU-TENANT-2',
                'tax_class_id' => $taxClass->id,
            ]);
        });

        // 3. Add Product to Tenant 3
        $tenant3->run(function () {
            ProductVariant::query()->delete();
            Product::query()->delete();
            $productType = ProductType::first();
            $taxClass = TaxClass::first();

            $p3 = Product::create([
                'product_type_id' => $productType->id,
                'status' => 'published',
                'attribute_data' => [
                    'name' => new Text('Product for Tenant 3'),
                ],
            ]);

            ProductVariant::create([
                'product_id' => $p3->id,
                'sku' => 'SKU-TENANT-3',
                'tax_class_id' => $taxClass->id,
            ]);
        });

        // 4. Verify Isolation for Tenant 1
        $tenant1->run(function () {
            $this->assertEquals(1, Product::count());
            $this->assertEquals(1, ProductVariant::where('sku', 'SKU-TENANT-1')->count());
            $this->assertEquals(0, ProductVariant::where('sku', 'SKU-TENANT-2')->count());
            $this->assertEquals(0, ProductVariant::where('sku', 'SKU-TENANT-3')->count());
        });

        // 5. Verify Isolation for Tenant 2
        $tenant2->run(function () {
            $this->assertEquals(1, Product::count());
            $this->assertEquals(0, ProductVariant::where('sku', 'SKU-TENANT-1')->count());
            $this->assertEquals(1, ProductVariant::where('sku', 'SKU-TENANT-2')->count());
            $this->assertEquals(0, ProductVariant::where('sku', 'SKU-TENANT-3')->count());
        });

        // 6. Verify Isolation for Tenant 3
        $tenant3->run(function () {
            $this->assertEquals(1, Product::count());
            $this->assertEquals(0, ProductVariant::where('sku', 'SKU-TENANT-1')->count());
            $this->assertEquals(0, ProductVariant::where('sku', 'SKU-TENANT-2')->count());
            $this->assertEquals(1, ProductVariant::where('sku', 'SKU-TENANT-3')->count());
        });
    }
}
