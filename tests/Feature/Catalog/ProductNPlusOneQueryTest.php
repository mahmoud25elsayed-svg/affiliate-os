<?php

declare(strict_types=1);

namespace Tests\Feature\Catalog;

use App\Domain\Catalog\Services\ProductService;
use App\Filament\Store\Resources\ProductResource;
use App\Models\Tenant;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Lunar\Models\Brand;
use Lunar\Models\Product;
use Tests\TestCase;

class ProductNPlusOneQueryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PlanSeeder::class);
    }

    public function test_product_resource_table_query_has_no_n_plus_one_queries(): void
    {
        /** @var Tenant $tenant */
        $tenant = $this->ensureTenantExists('catalog-nplusone');

        $tenant->run(function () use ($tenant) {
            Product::query()->delete();
            $brand = Brand::create(['name' => 'Apple']);
            $service = new ProductService;

            // Seed 20 products with variants, pricing, and brands
            for ($i = 1; $i <= 20; $i++) {
                $service->createProduct([
                    'name_ar' => "هاتف آيفون {$i}",
                    'sku' => "IPHONE-{$i}",
                    'brand_id' => $brand->id,
                    'retail_price' => 2000000,
                    'wholesale_price' => 1700000,
                    'min_price' => 1800000,
                    'max_price' => 2200000,
                ], $tenant);
            }

            $this->assertEquals(20, Product::count());

            DB::flushQueryLog();
            DB::enableQueryLog();

            // Execute the resource query with all relations eager loaded
            $products = ProductResource::getEloquentQuery()->get();

            // Simulate iterating over the rows as Filament Table does
            foreach ($products as $product) {
                $name = $product->translateAttribute('name');
                $sku = $product->variants->first()?->sku;
                $price = $product->variants->first()?->prices->first();
                $wholesale = $product->variants->first()?->wholesalePrice?->wholesalePriceMoney();
                $brandName = $product->brand?->name;

                $this->assertNotEmpty($name);
                $this->assertNotEmpty($sku);
            }

            $queries = DB::getQueryLog();
            $queryCount = count($queries);

            // With proper eager loading (products, variants, prices, wholesalePrice, brand, productType),
            // total queries must be strictly constant (under 10), NEVER 20+ queries!
            $this->assertLessThanOrEqual(
                8,
                $queryCount,
                "Detected potential N+1 query issue! Expected <= 8 queries for 20 products, got {$queryCount}."
            );
        });
    }
}
