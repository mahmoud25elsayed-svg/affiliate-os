<?php

declare(strict_types=1);

namespace Tests\Feature\Promotions;

use App\Domain\Catalog\Services\ProductService;
use App\Domain\Checkout\DTOs\CheckoutPayload;
use App\Domain\Checkout\Services\CouponService;
use App\Domain\Checkout\Services\OrderCreationService;
use App\Domain\Orders\Events\OrderDelivered;
use App\Domain\Promotions\Events\AbandonedCartDetected;
use App\Domain\Promotions\Events\FlashSaleStockDepleted;
use App\Domain\Promotions\Services\AbandonedCartService;
use App\Domain\Promotions\Services\LoyaltyPointsService;
use App\Domain\Promotions\Services\NewsletterService;
use App\Domain\Promotions\Services\PromotionPricingService;
use App\Models\Coupon;
use App\Models\Customer;
use App\Models\CustomerLoyaltyAccount;
use App\Models\DailyDeal;
use App\Models\FlashSale;
use App\Models\FlashSaleItem;
use App\Models\NewsletterSubscriber;
use App\Models\OrderEvent;
use App\Models\ProductBundle;
use App\Models\Tenant;
use App\Support\Location\Governorate;
use Database\Seeders\EgyptGovernoratesSeeder;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use Lunar\Models\ProductVariant;
use Tests\TestCase;

class PromotionsTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected Governorate $cairo;

    protected PromotionPricingService $promotionPricing;

    protected CouponService $couponService;

    protected LoyaltyPointsService $loyaltyService;

    protected AbandonedCartService $abandonedCartService;

    protected NewsletterService $newsletterService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PlanSeeder::class);
        $this->seed(EgyptGovernoratesSeeder::class);
        $this->tenant = $this->ensureTenantExists('promostore');

        $this->cairo = Governorate::where('code', 'CAI')->firstOrFail();

        $this->promotionPricing = app(PromotionPricingService::class);
        $this->couponService = app(CouponService::class);
        $this->loyaltyService = app(LoyaltyPointsService::class);
        $this->abandonedCartService = app(AbandonedCartService::class);
        $this->newsletterService = app(NewsletterService::class);
    }

    public function test_flash_sale_pricing_and_stock_allocation_cycle(): void
    {
        $this->tenant->run(function () {
            Event::fake([FlashSaleStockDepleted::class]);

            $productService = app(ProductService::class);
            $product = $productService->createProduct([
                'name_ar' => 'سماعات لاسلكية فاخرة',
                'sku' => 'EAR-01',
                'wholesale_price' => 30000,
                'min_price' => 40000,
                'max_price' => 60000,
                'retail_price' => 50000, // 500 EGP
                'status' => 'active',
            ]);

            /** @var ProductVariant $variant */
            $variant = $product->variants->first();

            $flashSale = FlashSale::create([
                'name' => 'فلاش سيل الجمعة البيضاء',
                'starts_at' => now()->subHour(),
                'ends_at' => now()->addDays(2),
                'is_active' => true,
            ]);

            $flashItem = FlashSaleItem::create([
                'flash_sale_id' => $flashSale->id,
                'product_variant_id' => $variant->id,
                'flash_price_minor' => 35000, // 350 EGP
                'allocated_quantity' => 2,
                'sold_quantity' => 0,
                'max_per_order' => 1,
            ]);

            // 1. Effective price is 350 EGP
            $pricing = $this->promotionPricing->getEffectiveVariantPrice($variant);
            $this->assertTrue($pricing['is_promotional']);
            $this->assertSame('flash_sale', $pricing['promotion_type']);
            $this->assertSame(35000, $pricing['price_minor']);
            $this->assertSame(50000, $pricing['original_price_minor']);

            // 2. Buy 1 unit via OrderCreationService
            $orderService = app(OrderCreationService::class);
            $order1 = $orderService->create(new CheckoutPayload(
                customer_name: 'محمد علي',
                customer_phone: '01012345678',
                governorate_id: $this->cairo->id,
                city: 'القاهرة',
                address: 'شارع المعز',
                items: [['purchasable_id' => $variant->id, 'quantity' => 1]],
                payment_method: 'cod'
            ));

            $flashItem->refresh();
            $this->assertSame(1, $flashItem->sold_quantity);
            $this->assertTrue($flashItem->isAvailable());

            // 3. Buy 2nd unit - exhausts quota
            $order2 = $orderService->create(new CheckoutPayload(
                customer_name: 'أحمد محمود',
                customer_phone: '01112345678',
                governorate_id: $this->cairo->id,
                city: 'القاهرة',
                address: 'شارع النصر',
                items: [['purchasable_id' => $variant->id, 'quantity' => 1]],
                payment_method: 'cod'
            ));

            $flashItem->refresh();
            $this->assertSame(2, $flashItem->sold_quantity);
            $this->assertFalse($flashItem->isAvailable());
            Event::assertDispatched(FlashSaleStockDepleted::class);

            // 4. Effective price reverts to standard 500 EGP
            $revertedPricing = $this->promotionPricing->getEffectiveVariantPrice($variant);
            $this->assertFalse($revertedPricing['is_promotional']);
            $this->assertSame(50000, $revertedPricing['price_minor']);
        });
    }

    public function test_coupon_conflict_rule_percentage_or_fixed_rejected_when_only_flash_items_in_cart(): void
    {
        $this->tenant->run(function () {
            $productService = app(ProductService::class);
            $product = $productService->createProduct([
                'name_ar' => 'ساعة ذكية',
                'sku' => 'SMART-01',
                'wholesale_price' => 20000,
                'min_price' => 30000,
                'max_price' => 50000,
                'retail_price' => 40000, // 400 EGP
                'status' => 'active',
            ]);

            /** @var ProductVariant $variant */
            $variant = $product->variants->first();

            $flashSale = FlashSale::create([
                'name' => 'فلاش سيل',
                'starts_at' => now()->subHour(),
                'ends_at' => now()->addDays(1),
                'is_active' => true,
            ]);

            FlashSaleItem::create([
                'flash_sale_id' => $flashSale->id,
                'product_variant_id' => $variant->id,
                'flash_price_minor' => 25000,
                'allocated_quantity' => 10,
                'sold_quantity' => 0,
            ]);

            Coupon::create([
                'code' => 'SAVE10',
                'name' => 'خصم 10%',
                'type' => Coupon::TYPE_PERCENTAGE,
                'value_minor' => 10,
                'is_active' => true,
            ]);

            // Cart has only this flash sale item
            $items = [
                [
                    'variant' => $variant,
                    'quantity' => 1,
                    'unit_price_minor' => 25000,
                ],
            ];

            $coupon = Coupon::where('code', 'SAVE10')->firstOrFail();

            $this->expectException(ValidationException::class);
            $this->expectExceptionMessage('هذا الكوبون لا يسري على المنتجات المشمولة بعروض التخفيض الفوري (الفلاش سيل)');

            $this->promotionPricing->getCouponEligibleSubtotal($items, $coupon);
        });
    }

    public function test_coupon_conflict_rule_percentage_discounts_only_non_flash_items_in_mixed_cart(): void
    {
        $this->tenant->run(function () {
            $productService = app(ProductService::class);

            // Item 1: Flash sale item (300 EGP regular -> 200 EGP flash)
            $flashProduct = $productService->createProduct([
                'name_ar' => 'منتج فلاش',
                'sku' => 'FL-01',
                'wholesale_price' => 15000,
                'min_price' => 25000,
                'max_price' => 35000,
                'retail_price' => 30000,
                'status' => 'active',
            ]);
            /** @var ProductVariant $flashVariant */
            $flashVariant = $flashProduct->variants->first();

            $flashSale = FlashSale::create([
                'name' => 'فلاش سيل مختلط',
                'starts_at' => now()->subHour(),
                'ends_at' => now()->addDays(1),
                'is_active' => true,
            ]);
            FlashSaleItem::create([
                'flash_sale_id' => $flashSale->id,
                'product_variant_id' => $flashVariant->id,
                'flash_price_minor' => 20000,
                'allocated_quantity' => 10,
                'sold_quantity' => 0,
            ]);

            // Item 2: Regular non-flash item (100 EGP = 10000 minor)
            $regProduct = $productService->createProduct([
                'name_ar' => 'منتج عادي',
                'sku' => 'REG-01',
                'wholesale_price' => 5000,
                'min_price' => 8000,
                'max_price' => 15000,
                'retail_price' => 10000,
                'status' => 'active',
            ]);
            /** @var ProductVariant $regVariant */
            $regVariant = $regProduct->variants->first();

            Coupon::create([
                'code' => 'SAVE10',
                'name' => 'خصم 10%',
                'type' => Coupon::TYPE_PERCENTAGE,
                'value_minor' => 10, // 10%
                'is_active' => true,
            ]);

            $items = [
                [
                    'variant' => $flashVariant,
                    'quantity' => 1,
                    'unit_price_minor' => 20000, // 200 EGP
                ],
                [
                    'variant' => $regVariant,
                    'quantity' => 1,
                    'unit_price_minor' => 10000, // 100 EGP
                ],
            ];

            // Total subtotal: 300 EGP (30,000 piasters)
            // Eligible subtotal for 10% discount: ONLY regular item (10,000 piasters)
            // Expected discount: 10% of 10,000 = 1,000 piasters (10 EGP), NOT 3,000!
            $coupon = Coupon::where('code', 'SAVE10')->firstOrFail();
            $eligibleSubtotal = $this->promotionPricing->getCouponEligibleSubtotal($items, $coupon);
            $this->assertSame(10000, $eligibleSubtotal);

            $result = $this->couponService->validateAndCalculate(
                code: 'SAVE10',
                subtotalMinor: 30000,
                itemIds: [$flashVariant->id, $regVariant->id],
                categoryIds: [],
                customerPhoneE164: '+201012345678',
                eligibleSubtotalMinor: $eligibleSubtotal
            );

            $this->assertTrue($result->isValid);
            $this->assertSame(1000, $result->discountMinor);
        });
    }

    public function test_free_shipping_coupon_stacks_with_flash_sale(): void
    {
        $this->tenant->run(function () {
            $productService = app(ProductService::class);
            $product = $productService->createProduct([
                'name_ar' => 'منتج فلاش شحن مجاني',
                'sku' => 'SHIP-FL-01',
                'wholesale_price' => 10000,
                'min_price' => 15000,
                'max_price' => 25000,
                'retail_price' => 20000,
                'status' => 'active',
            ]);
            /** @var ProductVariant $variant */
            $variant = $product->variants->first();

            $flashSale = FlashSale::create([
                'name' => 'فلاش سيل مع شحن مجاني',
                'starts_at' => now()->subHour(),
                'ends_at' => now()->addDays(1),
                'is_active' => true,
            ]);
            FlashSaleItem::create([
                'flash_sale_id' => $flashSale->id,
                'product_variant_id' => $variant->id,
                'flash_price_minor' => 15000,
                'allocated_quantity' => 5,
                'sold_quantity' => 0,
            ]);

            Coupon::create([
                'code' => 'FREESHIP',
                'name' => 'شحن مجاني',
                'type' => Coupon::TYPE_FREE_SHIPPING,
                'value_minor' => 0,
                'is_active' => true,
            ]);

            $items = [
                [
                    'variant' => $variant,
                    'quantity' => 1,
                    'unit_price_minor' => 15000,
                ],
            ];

            $coupon = Coupon::where('code', 'FREESHIP')->firstOrFail();
            $eligibleSubtotal = $this->promotionPricing->getCouponEligibleSubtotal($items, $coupon);
            $this->assertSame(15000, $eligibleSubtotal);

            $result = $this->couponService->validateAndCalculate(
                code: 'FREESHIP',
                subtotalMinor: 15000,
                itemIds: [$variant->id],
                categoryIds: [],
                customerPhoneE164: '+201012345678',
                eligibleSubtotalMinor: $eligibleSubtotal
            );

            $this->assertTrue($result->isValid);
            $this->assertTrue($result->isFreeShipping);
        });
    }

    public function test_daily_deal_applies_on_deal_date_and_expires_afterwards(): void
    {
        $this->tenant->run(function () {
            $productService = app(ProductService::class);
            $product = $productService->createProduct([
                'name_ar' => 'صفقة اليوم شاشة سمارت',
                'sku' => 'TV-01',
                'wholesale_price' => 300000,
                'min_price' => 350000,
                'max_price' => 500000,
                'retail_price' => 400000, // 4,000 EGP
                'status' => 'active',
            ]);
            /** @var ProductVariant $variant */
            $variant = $product->variants->first();

            // Deal for today: 20% discount
            $deal = DailyDeal::create([
                'product_id' => $product->id,
                'deal_date' => Carbon::today(),
                'discount_type' => 'percentage',
                'discount_value' => 20, // 20% off 400,000 => 320,000 (3,200 EGP)
                'banner_title' => 'عرض شاشة سمارت لليوم فقط',
                'is_active' => true,
            ]);

            $todayPricing = $this->promotionPricing->getEffectiveVariantPrice($variant);
            $this->assertTrue($todayPricing['is_promotional']);
            $this->assertSame('daily_deal', $todayPricing['promotion_type']);
            $this->assertSame(320000, $todayPricing['price_minor']);

            // If deal was yesterday
            $deal->update(['deal_date' => Carbon::yesterday()]);
            $yesterdayPricing = $this->promotionPricing->getEffectiveVariantPrice($variant);
            $this->assertFalse($yesterdayPricing['is_promotional']);
            $this->assertSame(400000, $yesterdayPricing['price_minor']);
        });
    }

    public function test_product_bundle_buy_x_get_y_evaluation(): void
    {
        $this->tenant->run(function () {
            $productService = app(ProductService::class);
            $prodA = $productService->createProduct([
                'name_ar' => 'قميص رجالي قطني',
                'sku' => 'SHIRT-01',
                'wholesale_price' => 15000,
                'min_price' => 20000,
                'max_price' => 30000,
                'retail_price' => 25000,
                'status' => 'active',
            ]);
            $prodB = $productService->createProduct([
                'name_ar' => 'كاب صيفي',
                'sku' => 'CAP-01',
                'wholesale_price' => 3000,
                'min_price' => 5000,
                'max_price' => 10000,
                'retail_price' => 8000,
                'status' => 'active',
            ]);

            $variantA = $prodA->variants->first();
            $variantB = $prodB->variants->first();
            $this->assertNotNull($variantA);
            $this->assertNotNull($variantB);

            $bundle = ProductBundle::create([
                'name_ar' => 'اشترِ 2 قميص واحصل على كاب مجاناً',
                'type' => ProductBundle::TYPE_BUY_X_GET_Y,
                'conditions' => [
                    'buy_variant_id' => $variantA->id,
                    'buy_quantity' => 2,
                    'get_variant_id' => $variantB->id,
                    'get_quantity' => 1,
                ],
                'discount_type' => 'percentage',
                'discount_value' => 100, // free
                'is_active' => true,
            ]);

            $res = $bundle->evaluate([$variantA->id => 2]);
            $this->assertTrue($res['applies']);
            $this->assertSame($variantB->id, $res['free_variant_id']);
            $this->assertSame(1, $res['free_quantity']);

            $resFail = $bundle->evaluate([$variantA->id => 1]);
            $this->assertFalse($resFail['applies']);
        });
    }

    public function test_loyalty_points_awarded_on_order_delivered_and_idempotent(): void
    {
        $this->tenant->run(function () {
            $customer = Customer::create([
                'first_name' => 'عميل',
                'last_name' => 'مخلص',
                'email' => 'loyal@test.com',
                'phone_e164' => '+201099998888',
                'is_active' => true,
            ]);

            // Create an order
            $productService = app(ProductService::class);
            $product = $productService->createProduct([
                'name_ar' => 'منتج نقاط الولاء',
                'sku' => 'LOYAL-01',
                'wholesale_price' => 5000,
                'min_price' => 8000,
                'max_price' => 15000,
                'retail_price' => 10000, // 100 EGP
                'status' => 'active',
            ]);
            /** @var ProductVariant $variant */
            $variant = $product->variants->first();

            $orderService = app(OrderCreationService::class);
            $order = $orderService->create(new CheckoutPayload(
                customer_name: 'عميل مخلص',
                customer_phone: '01099998888',
                governorate_id: $this->cairo->id,
                city: 'القاهرة',
                address: 'وسط البلد',
                items: [['purchasable_id' => $variant->id, 'quantity' => 2]],
                payment_method: 'cod',
                customer_id: $customer->id
            ));

            $orderTotal = is_object($order->total) && isset($order->total->value) ? (int) $order->total->value : (int) ($order->total ?? 0);
            $expectedPoints = (int) floor($orderTotal / 1000); // 1 pt per 10 EGP

            $orderEvent = OrderEvent::create([
                'tenant_id' => 'promostore',
                'order_id' => $order->id,
                'from_status' => 'shipped',
                'to_status' => 'delivered',
                'actor_type' => 'system',
                'created_at' => now(),
            ]);

            // Dispatch OrderDelivered event
            Event::dispatch(new OrderDelivered($order, $orderEvent));

            $account = CustomerLoyaltyAccount::where('customer_id', $customer->id)->first();
            $this->assertNotNull($account);
            $this->assertSame($expectedPoints, $account->points_balance);
            $this->assertSame(1, $account->transactions()->count());

            // Fire event again for same order - must be idempotent (no points added)
            Event::dispatch(new OrderDelivered($order, $orderEvent));

            $account->refresh();
            $this->assertSame($expectedPoints, $account->points_balance);
            $this->assertSame(1, $account->transactions()->count());
        });
    }

    public function test_loyalty_points_redemption_and_overdraft_prevention(): void
    {
        $this->tenant->run(function () {
            $customer = Customer::create([
                'first_name' => 'نادر',
                'last_name' => 'سمير',
                'email' => 'nader@test.com',
                'phone_e164' => '+201211112222',
                'is_active' => true,
            ]);

            $this->loyaltyService->awardPoints(
                customer: $customer,
                points: 100,
                source: 'bonus',
                description: 'رصيد نقاط ترحيبي'
            );

            $account = $this->loyaltyService->getOrCreateAccount($customer);
            $this->assertSame(100, $account->points_balance);

            // Redeem 40 points => 400 minor units (4 EGP)
            $discountMinor = $this->loyaltyService->redeemPoints(
                customer: $customer,
                points: 40,
                description: 'استبدال نقاط في الطلب'
            );

            $this->assertSame(400, $discountMinor);
            $account->refresh();
            $this->assertSame(60, $account->points_balance);

            // Overdraft attempt (trying to redeem 70 points when only 60 available)
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('رصيد نقاط الولاء غير كافٍ');
            $this->loyaltyService->redeemPoints($customer, 70);
        });
    }

    public function test_abandoned_cart_recording_scanning_and_recovery_by_token(): void
    {
        $this->tenant->run(function () {
            Event::fake([AbandonedCartDetected::class]);

            // 1. Record an abandoned cart
            $cart = $this->abandonedCartService->recordCart(
                sessionId: 'cart_token_abc123',
                customerId: null,
                cartData: ['raw_cart' => [1 => 2]],
                subtotalMinor: 50000,
                itemsCount: 2,
                phone: '+201055556666',
                name: 'طارق حسام',
                email: 'cart_abandoner@test.com'
            );

            $this->assertNotNull($cart->recovery_token);
            $this->assertFalse($cart->isRecovered());

            // 2. Set last_active_at to 2 hours ago
            $cart->update(['last_active_at' => now()->subHours(2)]);

            // 3. Run scan command
            $this->artisan('promotions:scan-abandoned-carts', ['--minutes' => 30])
                ->assertSuccessful();

            Event::assertDispatched(AbandonedCartDetected::class);

            $cart->refresh();
            $this->assertNotNull($cart->reminded_at);

            // 4. Recover by token
            $recovered = $this->abandonedCartService->recoverByToken($cart->recovery_token);
            $this->assertNotNull($recovered);
            $this->assertTrue($recovered->isRecovered());
        });
    }

    public function test_newsletter_subscription_flow_and_duplicate_handling(): void
    {
        $this->tenant->run(function () {
            // 1. Fresh subscription
            $res1 = $this->newsletterService->subscribe('subscriber@test.com', 'سارة جمال');
            $this->assertTrue($res1['success']);
            $this->assertSame('تم اشتراكك بنجاح في النشرة البريدية!', $res1['message']);

            $this->assertDatabaseHas('newsletter_subscribers', [
                'email' => 'subscriber@test.com',
                'name' => 'سارة جمال',
                'is_active' => true,
            ]);

            // 2. Duplicate subscription attempt (idempotent, no error)
            $res2 = $this->newsletterService->subscribe('subscriber@test.com');
            $this->assertTrue($res2['success']);
            $this->assertSame('أنت مشترك بالفعل في نشرتنا البريدية!', $res2['message']);
            $this->assertSame(1, NewsletterSubscriber::where('email', 'subscriber@test.com')->count());

            // 3. Unsubscribe then re-subscribe
            $this->newsletterService->unsubscribe('subscriber@test.com');
            $this->assertDatabaseHas('newsletter_subscribers', [
                'email' => 'subscriber@test.com',
                'is_active' => false,
            ]);

            $res3 = $this->newsletterService->subscribe('subscriber@test.com');
            $this->assertTrue($res3['success']);
            $this->assertSame('تمت إعادة تفعيل اشتراكك في النشرة البريدية بنجاح!', $res3['message']);
            $this->assertDatabaseHas('newsletter_subscribers', [
                'email' => 'subscriber@test.com',
                'is_active' => true,
            ]);
        });
    }
}
