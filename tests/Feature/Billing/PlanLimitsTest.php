<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Domain\Catalog\Services\ProductService;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Support\Billing\Exceptions\LimitExceededException;
use App\Support\Billing\Limits;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Lunar\Models\Product;
use Tests\TestCase;

class PlanLimitsTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_limit_is_strictly_enforced_by_service(): void
    {
        // 1. Create a plan with a limit of 10 products
        /** @var Plan $limitedPlan */
        $limitedPlan = Plan::create([
            'slug' => 'test-limited-10',
            'name_ar' => 'باقة تجريبية 10',
            'name_en' => 'Test Limited 10',
            'price_monthly' => 10000,
            'price_yearly' => 100000,
            'trial_days' => 14,
            'limits' => [
                'products' => 10,
                'monthly_orders' => 100,
                'affiliates' => 5,
                'staff' => 2,
                'storage_mb' => 100,
            ],
            'features' => [
                'affiliate' => true,
            ],
            'is_active' => true,
        ]);

        /** @var Tenant $tenant */
        $tenant = $this->ensureTenantExists('limit-test-store');

        $subscription = $tenant->subscriptions()->create([
            'plan_id' => $limitedPlan->id,
            'status' => Subscription::STATUS_ACTIVE,
            'billing_cycle' => Subscription::CYCLE_MONTHLY,
            'current_period_starts_at' => now(),
            'current_period_ends_at' => now()->addMonth(),
        ]);

        $productService = new ProductService;

        // Run in tenant context
        $tenant->run(function () use ($tenant, $productService) {
            Product::query()->delete();

            // Create 10 products successfully
            for ($i = 1; $i <= 10; $i++) {
                $product = $productService->createProduct([
                    'name' => "Product {$i}",
                    'status' => 'published',
                ], $tenant);

                $this->assertNotNull($product);
            }

            $this->assertEquals(10, Product::count());
            $this->assertEquals(10, Limits::usage('products', $tenant));
            $this->assertFalse(Limits::check('products', $tenant, 1));

            // Attempt to create product 11 => Must throw LimitExceededException
            $thrown = false;

            try {
                $productService->createProduct([
                    'name' => 'Product 11 - Should Fail',
                    'status' => 'published',
                ], $tenant);
            } catch (LimitExceededException $e) {
                $thrown = true;
                $this->assertEquals('products', $e->limitKey);
                $this->assertEquals(10, $e->maxLimit);
                $this->assertEquals(10, $e->currentUsage);
                $this->assertStringContainsString('10', $e->getMessage());
            }

            $this->assertTrue($thrown, 'Expected LimitExceededException was not thrown when creating 11th product.');
            $this->assertEquals(10, Product::count());
        });
    }
}
