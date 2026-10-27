<?php

declare(strict_types=1);

namespace Tests\Feature\Catalog;

use App\Domain\Catalog\Services\ProductService;
use App\Models\Tenant;
use App\Models\WholesalePrice;
use Database\Seeders\PlanSeeder;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Lunar\Models\Brand;
use Lunar\Models\Product;
use Tests\TestCase;

class ProductCrudAndPricingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PlanSeeder::class);
    }

    public function test_can_create_product_with_wholesale_and_affiliate_pricing(): void
    {
        /** @var Tenant $tenant */
        $tenant = $this->ensureTenantExists('catalog-store-1');

        $tenant->run(function () use ($tenant) {
            Product::query()->delete();
            $brand = Brand::create(['name' => 'Nike']);

            $service = new ProductService;
            $product = $service->createProduct([
                'name_ar' => 'حذاء رياضي أنيق',
                'name_en' => 'Elegant Sports Shoes',
                'sku' => 'SHOE-NK-001',
                'brand_id' => $brand->id,
                'retail_price' => 35000,     // 350.00 EGP
                'wholesale_price' => 20000,  // 200.00 EGP
                'min_price' => 25000,        // 250.00 EGP (>= wholesale)
                'max_price' => 45000,        // 450.00 EGP (>= min)
                'compare_at_price' => 40000, // 400.00 EGP
                'status' => WholesalePrice::STATUS_ACTIVE,
            ], $tenant);

            $this->assertNotNull($product);
            $this->assertEquals(1, Product::count());

            $variant = $product->variants->first();
            $this->assertNotNull($variant);
            $this->assertEquals('SHOE-NK-001', $variant->sku);

            /** @var WholesalePrice|null $pricing */
            $pricing = $variant->wholesalePrice;
            $this->assertNotNull($pricing);
            $this->assertEquals(20000, $pricing->wholesale_price_minor);
            $this->assertEquals(25000, $pricing->min_price_minor);
            $this->assertEquals(45000, $pricing->max_price_minor);
            $this->assertEquals(40000, $pricing->compare_at_price_minor);
            $this->assertEquals('200.00 ج.م', $pricing->wholesalePriceMoney()->format('ar'));
            $this->assertEquals('250.00 ج.م', $pricing->minPriceMoney()->format('ar'));
            $this->assertEquals('450.00 ج.م', $pricing->maxPriceMoney()->format('ar'));
            $this->assertTrue($pricing->isActive());
        });
    }

    public function test_pricing_constraints_strictly_enforce_max_gte_min_gte_wholesale(): void
    {
        /** @var Tenant $tenant */
        $tenant = $this->ensureTenantExists('catalog-store-pricing');

        $tenant->run(function () use ($tenant) {
            $service = new ProductService;

            // 1. Test min < wholesale => MUST throw DomainException
            $thrownMin = false;

            try {
                $service->createProduct([
                    'name_ar' => 'منتج مخالف 1',
                    'sku' => 'FAIL-MIN-001',
                    'wholesale_price' => 30000, // 300 EGP
                    'min_price' => 25000,       // 250 EGP (< wholesale!)
                    'max_price' => 40000,
                ], $tenant);
            } catch (DomainException $e) {
                $thrownMin = true;
                $this->assertStringContainsString('أكبر من أو يساوي سعر الجملة', $e->getMessage());
            }
            $this->assertTrue($thrownMin, 'Expected DomainException for min < wholesale was not thrown.');

            // 2. Test max < min => MUST throw DomainException
            $thrownMax = false;

            try {
                $service->createProduct([
                    'name_ar' => 'منتج مخالف 2',
                    'sku' => 'FAIL-MAX-002',
                    'wholesale_price' => 20000, // 200 EGP
                    'min_price' => 30000,       // 300 EGP
                    'max_price' => 28000,       // 280 EGP (< min!)
                ], $tenant);
            } catch (DomainException $e) {
                $thrownMax = true;
                $this->assertStringContainsString('أكبر من أو يساوي الحد الأدنى', $e->getMessage());
            }
            $this->assertTrue($thrownMax, 'Expected DomainException for max < min was not thrown.');
        });
    }

    public function test_product_status_transitions_and_pause_reason(): void
    {
        /** @var Tenant $tenant */
        $tenant = $this->ensureTenantExists('catalog-store-status');

        $tenant->run(function () use ($tenant) {
            $service = new ProductService;
            $product = $service->createProduct([
                'name_ar' => 'ساعة ذكية',
                'sku' => 'WATCH-001',
                'wholesale_price' => 15000,
                'min_price' => 20000,
                'max_price' => 30000,
                'status' => WholesalePrice::STATUS_ACTIVE,
            ], $tenant);

            // 1. Pausing without reason MUST throw DomainException
            $thrownPause = false;

            try {
                $service->updateStatus($product, WholesalePrice::STATUS_PAUSED, '');
            } catch (DomainException $e) {
                $thrownPause = true;
                $this->assertStringContainsString('سبب إيقاف المنتج', $e->getMessage());
            }
            $this->assertTrue($thrownPause);

            // 2. Pausing with reason succeeds
            $service->updateStatus($product, WholesalePrice::STATUS_PAUSED, 'نفاد المخزون مؤقتًا من المورد');
            /** @var WholesalePrice $pricing */
            $pricing = $product->variants->first()->wholesalePrice;
            $this->assertTrue($pricing->isPaused());
            $this->assertEquals('نفاد المخزون مؤقتًا من المورد', $pricing->pause_reason);

            // 3. Reactivating product
            $service->updateStatus($product, WholesalePrice::STATUS_ACTIVE);
            $pricing->refresh();
            $this->assertTrue($pricing->isActive());
        });
    }
}
