<?php

declare(strict_types=1);

namespace Tests\Feature\Checkout;

use App\Domain\Checkout\Services\CouponService;
use App\Models\Coupon;
use App\Models\Tenant;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CouponTruthTableTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected CouponService $couponService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PlanSeeder::class);
        $this->tenant = $this->ensureTenantExists('couponstore');
        $this->couponService = app(CouponService::class);
    }

    public function test_case_01_percentage_discount_calculated_correctly(): void
    {
        $this->tenant->run(function () {
            $coupon = Coupon::create([
                'code' => 'PERC10',
                'name' => '10% Discount',
                'type' => Coupon::TYPE_PERCENTAGE,
                'value_minor' => 10, // 10%
                'is_active' => true,
            ]);

            // Subtotal: 500 EGP (50,000 piasters) => 10% = 50 EGP (5,000 piasters)
            $result = $this->couponService->validateAndCalculate('PERC10', 50000);

            $this->assertTrue($result->isValid);
            $this->assertSame(5000, $result->discountMinor);
            $this->assertFalse($result->isFreeShipping);
        });
    }

    public function test_case_02_percentage_discount_capped_by_max_discount(): void
    {
        $this->tenant->run(function () {
            Coupon::create([
                'code' => 'PERC50CAP',
                'name' => '50% with Cap',
                'type' => Coupon::TYPE_PERCENTAGE,
                'value_minor' => 50,
                'max_discount_minor' => 10000, // max 100 EGP
                'is_active' => true,
            ]);

            // Subtotal: 1000 EGP (100,000 piasters). 50% would be 50,000, but capped at 10,000
            $result = $this->couponService->validateAndCalculate('PERC50CAP', 100000);

            $this->assertTrue($result->isValid);
            $this->assertSame(10000, $result->discountMinor);
        });
    }

    public function test_case_03_percentage_discount_cannot_exceed_order_subtotal(): void
    {
        $this->tenant->run(function () {
            Coupon::create([
                'code' => 'PERC100',
                'name' => '100% Off',
                'type' => Coupon::TYPE_PERCENTAGE,
                'value_minor' => 150, // extreme 150%
                'is_active' => true,
            ]);

            $result = $this->couponService->validateAndCalculate('PERC100', 25000);

            $this->assertTrue($result->isValid);
            $this->assertSame(25000, $result->discountMinor); // capped at subtotal
        });
    }

    public function test_case_04_fixed_amount_discount(): void
    {
        $this->tenant->run(function () {
            Coupon::create([
                'code' => 'FIXED75',
                'name' => '75 EGP Off',
                'type' => Coupon::TYPE_FIXED,
                'value_minor' => 7500, // 75 EGP
                'is_active' => true,
            ]);

            $result = $this->couponService->validateAndCalculate('FIXED75', 40000);

            $this->assertTrue($result->isValid);
            $this->assertSame(7500, $result->discountMinor);
        });
    }

    public function test_case_05_fixed_discount_exceeding_subtotal_is_capped_at_subtotal(): void
    {
        $this->tenant->run(function () {
            Coupon::create([
                'code' => 'FIXED500',
                'name' => '500 EGP Off',
                'type' => Coupon::TYPE_FIXED,
                'value_minor' => 50000, // 500 EGP
                'is_active' => true,
            ]);

            // Subtotal is only 200 EGP (20,000 piasters)
            $result = $this->couponService->validateAndCalculate('FIXED500', 20000);

            $this->assertTrue($result->isValid);
            $this->assertSame(20000, $result->discountMinor);
        });
    }

    public function test_case_06_free_shipping_coupon(): void
    {
        $this->tenant->run(function () {
            Coupon::create([
                'code' => 'FREESHIP',
                'name' => 'Free Shipping',
                'type' => Coupon::TYPE_FREE_SHIPPING,
                'value_minor' => 0,
                'is_active' => true,
            ]);

            $result = $this->couponService->validateAndCalculate('FREESHIP', 30000);

            $this->assertTrue($result->isValid);
            $this->assertSame(0, $result->discountMinor);
            $this->assertTrue($result->isFreeShipping);
        });
    }

    public function test_case_07_expired_coupon_is_rejected(): void
    {
        $this->tenant->run(function () {
            Coupon::create([
                'code' => 'EXPIRED',
                'name' => 'Expired Coupon',
                'type' => Coupon::TYPE_FIXED,
                'value_minor' => 2000,
                'expires_at' => now()->subDay(),
                'is_active' => true,
            ]);

            $result = $this->couponService->validateAndCalculate('EXPIRED', 30000);

            $this->assertFalse($result->isValid);
            $this->assertStringContainsString('انتهت صلاحية', (string) $result->errorMessage);
        });
    }

    public function test_case_08_future_coupon_is_rejected(): void
    {
        $this->tenant->run(function () {
            Coupon::create([
                'code' => 'FUTURE',
                'name' => 'Future Coupon',
                'type' => Coupon::TYPE_FIXED,
                'value_minor' => 2000,
                'starts_at' => now()->addDays(2),
                'is_active' => true,
            ]);

            $result = $this->couponService->validateAndCalculate('FUTURE', 30000);

            $this->assertFalse($result->isValid);
            $this->assertStringContainsString('لم يبدأ', (string) $result->errorMessage);
        });
    }

    public function test_case_09_valid_coupon_within_date_window(): void
    {
        $this->tenant->run(function () {
            Coupon::create([
                'code' => 'VALIDWINDOW',
                'name' => 'Valid Window Coupon',
                'type' => Coupon::TYPE_FIXED,
                'value_minor' => 3000,
                'starts_at' => now()->subDay(),
                'expires_at' => now()->addDay(),
                'is_active' => true,
            ]);

            $result = $this->couponService->validateAndCalculate('VALIDWINDOW', 30000);

            $this->assertTrue($result->isValid);
            $this->assertSame(3000, $result->discountMinor);
        });
    }

    public function test_case_10_inactive_coupon_is_rejected(): void
    {
        $this->tenant->run(function () {
            Coupon::create([
                'code' => 'DISABLED',
                'name' => 'Disabled Coupon',
                'type' => Coupon::TYPE_FIXED,
                'value_minor' => 5000,
                'is_active' => false,
            ]);

            $result = $this->couponService->validateAndCalculate('DISABLED', 30000);

            $this->assertFalse($result->isValid);
            $this->assertStringContainsString('غير صالح أو غير موجود', (string) $result->errorMessage);
        });
    }

    public function test_case_11_non_existent_coupon_code_is_rejected(): void
    {
        $this->tenant->run(function () {
            $result = $this->couponService->validateAndCalculate('TOTALLY_FAKE_CODE', 30000);

            $this->assertFalse($result->isValid);
            $this->assertStringContainsString('غير صالح أو غير موجود', (string) $result->errorMessage);
        });
    }

    public function test_case_12_global_usage_limit_reached_is_rejected(): void
    {
        $this->tenant->run(function () {
            Coupon::create([
                'code' => 'LIMITED100',
                'name' => 'Max 100 Uses',
                'type' => Coupon::TYPE_FIXED,
                'value_minor' => 1000,
                'max_uses' => 100,
                'uses_count' => 100, // already reached
                'is_active' => true,
            ]);

            $result = $this->couponService->validateAndCalculate('LIMITED100', 30000);

            $this->assertFalse($result->isValid);
            $this->assertStringContainsString('استنفاد الحد الأقصى', (string) $result->errorMessage);
        });
    }

    public function test_case_13_global_usage_count_under_limit_is_allowed(): void
    {
        $this->tenant->run(function () {
            Coupon::create([
                'code' => 'UNDERLIMIT',
                'name' => 'Under Limit',
                'type' => Coupon::TYPE_FIXED,
                'value_minor' => 1000,
                'max_uses' => 50,
                'uses_count' => 49,
                'is_active' => true,
            ]);

            $result = $this->couponService->validateAndCalculate('UNDERLIMIT', 30000);

            $this->assertTrue($result->isValid);
            $this->assertSame(1000, $result->discountMinor);
        });
    }

    public function test_case_14_per_customer_usage_limit_reached_is_rejected(): void
    {
        $this->tenant->run(function () {
            $coupon = Coupon::create([
                'code' => 'ONCEPERCUST',
                'name' => 'Once Per Customer',
                'type' => Coupon::TYPE_FIXED,
                'value_minor' => 2000,
                'max_uses_per_customer' => 1,
                'is_active' => true,
            ]);

            $phone = '+201012345678';

            // First use
            $this->couponService->recordRedemption($coupon, 1, $phone, 2000);

            // Second attempt by the same phone
            $result = $this->couponService->validateAndCalculate(
                'ONCEPERCUST',
                30000,
                [],
                [],
                $phone
            );

            $this->assertFalse($result->isValid);
            $this->assertStringContainsString('استنفدت الحد المسموح لك', (string) $result->errorMessage);
        });
    }

    public function test_case_15_per_customer_usage_allowed_for_different_customer(): void
    {
        $this->tenant->run(function () {
            $coupon = Coupon::create([
                'code' => 'ONCEPERCUST2',
                'name' => 'Once Per Customer 2',
                'type' => Coupon::TYPE_FIXED,
                'value_minor' => 2000,
                'max_uses_per_customer' => 1,
                'is_active' => true,
            ]);

            // Redeemed by user 1
            $this->couponService->recordRedemption($coupon, 1, '+201011111111', 2000);

            // Attempt by user 2
            $result = $this->couponService->validateAndCalculate(
                'ONCEPERCUST2',
                30000,
                [],
                [],
                '+201022222222'
            );

            $this->assertTrue($result->isValid);
            $this->assertSame(2000, $result->discountMinor);
        });
    }

    public function test_case_16_min_order_value_not_met_is_rejected(): void
    {
        $this->tenant->run(function () {
            Coupon::create([
                'code' => 'MIN500',
                'name' => 'Min 500 EGP',
                'type' => Coupon::TYPE_FIXED,
                'value_minor' => 5000,
                'min_order_value_minor' => 50000, // 500 EGP
                'is_active' => true,
            ]);

            // Cart subtotal is only 300 EGP (30,000 piasters)
            $result = $this->couponService->validateAndCalculate('MIN500', 30000);

            $this->assertFalse($result->isValid);
            $this->assertStringContainsString('الحد الأدنى للطلب', (string) $result->errorMessage);
        });
    }

    public function test_case_17_min_order_value_met_is_allowed(): void
    {
        $this->tenant->run(function () {
            Coupon::create([
                'code' => 'MIN500PASS',
                'name' => 'Min 500 EGP Pass',
                'type' => Coupon::TYPE_FIXED,
                'value_minor' => 5000,
                'min_order_value_minor' => 50000, // 500 EGP
                'is_active' => true,
            ]);

            // Cart subtotal is 600 EGP (60,000 piasters)
            $result = $this->couponService->validateAndCalculate('MIN500PASS', 60000);

            $this->assertTrue($result->isValid);
            $this->assertSame(5000, $result->discountMinor);
        });
    }

    public function test_case_18_category_restricted_coupon(): void
    {
        $this->tenant->run(function () {
            Coupon::create([
                'code' => 'SHOESONLY',
                'name' => 'Shoes Only',
                'type' => Coupon::TYPE_PERCENTAGE,
                'value_minor' => 20,
                'restricted_categories' => [10, 20],
                'is_active' => true,
            ]);

            // Cart with category 99 (not in [10, 20])
            $resultFail = $this->couponService->validateAndCalculate('SHOESONLY', 50000, [], [99]);
            $this->assertFalse($resultFail->isValid);
            $this->assertStringContainsString('غير متاح للأقسام', (string) $resultFail->errorMessage);

            // Cart with category 20 (matches)
            $resultPass = $this->couponService->validateAndCalculate('SHOESONLY', 50000, [], [20, 99]);
            $this->assertTrue($resultPass->isValid);
            $this->assertSame(10000, $resultPass->discountMinor);
        });
    }

    public function test_case_19_product_restricted_coupon(): void
    {
        $this->tenant->run(function () {
            Coupon::create([
                'code' => 'VIPROD',
                'name' => 'VIP Product Only',
                'type' => Coupon::TYPE_FIXED,
                'value_minor' => 4000,
                'restricted_products' => [101, 102],
                'is_active' => true,
            ]);

            // Cart with items [50, 60] (no match)
            $resultFail = $this->couponService->validateAndCalculate('VIPROD', 50000, [50, 60]);
            $this->assertFalse($resultFail->isValid);
            $this->assertStringContainsString('غير متاح للمنتجات', (string) $resultFail->errorMessage);

            // Cart with items [101, 60] (matches 101)
            $resultPass = $this->couponService->validateAndCalculate('VIPROD', 50000, [101, 60]);
            $this->assertTrue($resultPass->isValid);
            $this->assertSame(4000, $resultPass->discountMinor);
        });
    }

    public function test_case_20_case_insensitive_code_matching(): void
    {
        $this->tenant->run(function () {
            Coupon::create([
                'code' => 'SUMMER2026',
                'name' => 'Summer Sale',
                'type' => Coupon::TYPE_FIXED,
                'value_minor' => 1500,
                'is_active' => true,
            ]);

            // Input is lowercase 'summer2026'
            $result = $this->couponService->validateAndCalculate('  summer2026  ', 20000);

            $this->assertTrue($result->isValid);
            $this->assertSame(1500, $result->discountMinor);
        });
    }
}
