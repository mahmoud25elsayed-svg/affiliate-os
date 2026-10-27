<?php

declare(strict_types=1);

namespace Tests\Feature\Storefront;

use App\Domain\Catalog\Services\ProductService;
use App\Domain\Storefront\Services\StorefrontCacheService;
use App\Models\Tenant;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Lunar\Models\Product;
use Tests\TestCase;

class StorefrontCacheAndSuspensionTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PlanSeeder::class);
        $this->tenant = $this->ensureTenantExists('cachestore');
    }

    public function test_storefront_cache_service_remembers_and_invalidates_on_product_event(): void
    {
        $this->tenant->run(function () {
            $service = app(ProductService::class);
            $product = $service->createProduct([
                'name_ar' => 'منتج كاش أولي',
                'sku' => 'CACHE-001',
                'retail_price' => 10000,
                'wholesale_price' => 5000,
                'min_price' => 7000,
                'max_price' => 12000,
            ]);

            // Cache home data
            $cachedVal = StorefrontCacheService::remember('home_data', 3600, fn () => 'original_cache');
            $this->assertEquals('original_cache', $cachedVal);

            // Updating the product triggers saved event which calls flush()
            $product->update(['status' => 'draft']);

            // After event, cache should have been forgotten
            $tenantId = (string) tenant('id');
            $this->assertNull(Cache::get("storefront.{$tenantId}.home_data"));
        });
    }

    public function test_suspended_storefront_returns_503_with_arabic_notice(): void
    {
        $domain = $this->tenant->domains->first()?->domain ?? 'cachestore.test';

        // Suspend the tenant
        $this->tenant->update(['status' => Tenant::STATUS_SUSPENDED]);

        $response = $this->get("http://{$domain}/");

        $response->assertStatus(503);
        $response->assertSee('المتجر مغلق مؤقتًا');
        $response->assertSee($this->tenant->name);
    }
}
