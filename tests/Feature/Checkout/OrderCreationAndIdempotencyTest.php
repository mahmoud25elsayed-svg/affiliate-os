<?php

declare(strict_types=1);

namespace Tests\Feature\Checkout;

use App\Domain\Catalog\Services\ProductService;
use App\Domain\Checkout\DTOs\CheckoutPayload;
use App\Domain\Checkout\Services\OrderCreationService;
use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Models\Tenant;
use App\Support\Location\Governorate;
use Database\Seeders\EgyptGovernoratesSeeder;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Lunar\Models\Order;
use Lunar\Models\ProductVariant;
use Tests\TestCase;

class OrderCreationAndIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected Governorate $governorate;

    protected ProductVariant $variant;

    protected OrderCreationService $orderCreationService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PlanSeeder::class);
        $this->seed(EgyptGovernoratesSeeder::class);
        $this->tenant = $this->ensureTenantExists('ordershop');

        $this->governorate = Governorate::where('code', 'CAI')->firstOrFail();

        $this->tenant->run(function () {
            $productService = app(ProductService::class);
            $product = $productService->createProduct([
                'name_ar' => 'سماعات بلوتوث',
                'sku' => 'EAR-01',
                'wholesale_price' => 15000,
                'min_price' => 20000,
                'max_price' => 30000,
                'retail_price' => 25000, // 250 EGP
                'status' => 'active',
            ]);

            $this->variant = $product->variants->firstOrFail();
        });

        $this->orderCreationService = app(OrderCreationService::class);
    }

    public function test_double_click_or_duplicate_token_creates_exactly_one_order(): void
    {
        $this->tenant->run(function () {
            $idempotencyToken = 'token_abc_123_unique';

            $payload = new CheckoutPayload(
                customer_name: 'علي حسن',
                customer_phone: '01098765432',
                governorate_id: $this->governorate->id,
                city: 'المعادي',
                address: 'شارع 9 عمارة 4',
                notes: 'رنين الجرس مرتين',
                payment_method: 'cod',
                items: [
                    ['purchasable_id' => $this->variant->id, 'quantity' => 1],
                ],
                idempotency_token: $idempotencyToken
            );

            // First submission
            $order1 = $this->orderCreationService->create($payload);

            // Second submission with the exact same idempotency token (e.g. rapid double click)
            $order2 = $this->orderCreationService->create($payload);

            // Assert exactly one order was created in the database
            $this->assertSame(1, Order::count());
            $this->assertSame($order1->id, $order2->id);
            $this->assertSame($order1->reference, $order2->reference);
        });
    }

    public function test_spam_detection_flags_duplicate_order_from_same_phone_within_window(): void
    {
        $this->tenant->run(function () {
            $phone = '01123456789';

            $payload1 = new CheckoutPayload(
                customer_name: 'كريم محمود',
                customer_phone: $phone,
                governorate_id: $this->governorate->id,
                city: 'الجيزة',
                address: 'شارع الهرم الرئيسي',
                payment_method: 'cod',
                items: [
                    ['purchasable_id' => $this->variant->id, 'quantity' => 1],
                ],
                idempotency_token: 'first_unique_token_1'
            );

            // First order passes cleanly
            $order1 = $this->orderCreationService->create($payload1);
            $this->assertSame('pass', $order1->quality_result);

            // Second order placed 1 minute later with same phone and items, but different token
            $payload2 = new CheckoutPayload(
                customer_name: 'كريم محمود',
                customer_phone: $phone,
                governorate_id: $this->governorate->id,
                city: 'الجيزة',
                address: 'شارع الهرم الرئيسي',
                payment_method: 'cod',
                items: [
                    ['purchasable_id' => $this->variant->id, 'quantity' => 1],
                ],
                idempotency_token: 'second_unique_token_2'
            );

            $order2 = $this->orderCreationService->create($payload2);

            // Assert second order exists but is flagged for review
            $this->assertSame(2, Order::count());
            $this->assertSame('review', $order2->quality_result);
            $flags = is_string($order2->quality_flags) ? json_decode($order2->quality_flags, true) : $order2->quality_flags;
            $this->assertContains('duplicate_phone_and_items_within_5_minutes', (array) $flags);
        });
    }

    public function test_invalid_egyptian_phone_is_rejected(): void
    {
        $this->tenant->run(function () {
            $payload = new CheckoutPayload(
                customer_name: 'أحمد',
                customer_phone: '01912345678', // Invalid prefix 019
                governorate_id: $this->governorate->id,
                city: 'القاهرة',
                address: 'وسط البلد',
                items: [
                    ['purchasable_id' => $this->variant->id, 'quantity' => 1],
                ]
            );

            $this->expectException(ValidationException::class);
            $this->orderCreationService->create($payload);
        });
    }

    public function test_coupon_redemption_is_logged_in_database(): void
    {
        $this->tenant->run(function () {
            $coupon = Coupon::create([
                'code' => 'DISC50',
                'name' => '50 EGP Off',
                'type' => Coupon::TYPE_FIXED,
                'value_minor' => 5000,
                'is_active' => true,
            ]);

            $payload = new CheckoutPayload(
                customer_name: 'خالد سليم',
                customer_phone: '01234567890',
                governorate_id: $this->governorate->id,
                city: 'الزمالك',
                address: 'شارع 26 يوليو',
                payment_method: 'cod',
                coupon_code: 'DISC50',
                items: [
                    ['purchasable_id' => $this->variant->id, 'quantity' => 1],
                ]
            );

            $order = $this->orderCreationService->create($payload);

            $this->assertSame(5000, $order->discount_total->value);
            $this->assertSame(1, $coupon->fresh()->uses_count);

            // Assert coupon redemption record was written
            $redemption = CouponRedemption::where('coupon_id', $coupon->id)->first();
            $this->assertNotNull($redemption);
            $this->assertSame($order->id, $redemption->order_id);
            $this->assertSame(5000, $redemption->discount_minor);
        });
    }
}
