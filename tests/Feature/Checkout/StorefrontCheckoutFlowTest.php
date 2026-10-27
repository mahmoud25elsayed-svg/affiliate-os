<?php

declare(strict_types=1);

namespace Tests\Feature\Checkout;

use App\Domain\Catalog\Services\ProductService;
use App\Domain\Checkout\Services\StorefrontCartService;
use App\Livewire\Storefront\CartDrawer;
use App\Livewire\Storefront\CheckoutPage;
use App\Models\Tenant;
use App\Support\Location\Governorate;
use Database\Seeders\EgyptGovernoratesSeeder;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Lunar\Models\Order;
use Lunar\Models\ProductVariant;
use Tests\TestCase;

class StorefrontCheckoutFlowTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected string $domain;

    protected Governorate $cairo;

    protected ProductVariant $variant;

    protected function setUp(): void
    {
        parent::setUp();

        app()->setLocale('ar');
        $this->seed(PlanSeeder::class);
        $this->seed(EgyptGovernoratesSeeder::class);
        $this->tenant = $this->ensureTenantExists('flowstore');
        $this->domain = $this->tenant->domains->first()?->domain ?? 'flowstore.test';

        $this->cairo = Governorate::where('code', 'CAI')->firstOrFail();

        $this->tenant->run(function () {
            $productService = app(ProductService::class);
            $product = $productService->createProduct([
                'name_ar' => 'قميص أبيض كلاسيك',
                'sku' => 'SHIRT-WHT',
                'wholesale_price' => 20000,
                'min_price' => 30000,
                'max_price' => 45000,
                'retail_price' => 35000, // 350 EGP
                'status' => 'active',
            ]);

            $this->variant = $product->variants->firstOrFail();
        });
    }

    public function test_cart_page_renders_with_added_items(): void
    {
        $this->tenant->run(function () {
            $cartService = app(StorefrontCartService::class);
            $cartService->addItem($this->variant->id, 2);

            $response = $this->get("http://{$this->domain}/cart");

            $response->assertStatus(200);
            $response->assertSee('سلة المشتريات');
            $response->assertSee('قميص أبيض كلاسيك');
            $response->assertSee('700.00'); // 350 * 2 = 700 EGP
        });
    }

    public function test_cart_drawer_livewire_interactions(): void
    {
        $this->tenant->run(function () {
            $cartService = app(StorefrontCartService::class);
            $cartService->addItem($this->variant->id, 1);

            Livewire::test(CartDrawer::class)
                ->call('toggle')
                ->assertSee('قميص أبيض كلاسيك')
                ->assertSee('350.00')
                ->call('increment', $this->variant->id)
                ->assertSee('700.00')
                ->call('decrement', $this->variant->id)
                ->assertSee('350.00')
                ->call('removeItem', $this->variant->id)
                ->assertSee('سلتك فارغة حالياً');
        });
    }

    public function test_checkout_page_renders_form_and_governorates(): void
    {
        $this->tenant->run(function () {
            $cartService = app(StorefrontCartService::class);
            $cartService->addItem($this->variant->id, 1);

            $response = $this->get("http://{$this->domain}/checkout");

            $response->assertStatus(200);
            $response->assertSee('إتمام الشراء والدفع');
            $response->assertSee('بيانات التوصيل');
            $response->assertSee('القاهرة');
            $response->assertSee('الدفع نقدًا عند الاستلام');
        });
    }

    public function test_livewire_checkout_submission_creates_order_and_redirects_to_success(): void
    {
        $this->tenant->run(function () {
            $cartService = app(StorefrontCartService::class);
            $cartService->addItem($this->variant->id, 1);

            Livewire::test(CheckoutPage::class)
                ->set('customer_name', 'محمد الشناوي')
                ->set('customer_phone', '01012345678')
                ->set('governorate_id', $this->cairo->id)
                ->set('city', 'التجمع الخامس')
                ->set('address', 'شارع التسعين مجمع البنوك')
                ->set('notes', 'تسليم صباحاً')
                ->set('payment_method', 'cod')
                ->call('submitOrder')
                ->assertHasNoErrors()
                ->assertRedirect();

            // Assert order exists in database
            $order = Order::latest('id')->first();
            $this->assertNotNull($order);
            $this->assertSame('+201012345678', $order->customer_phone_e164);
            $this->assertSame('pending_confirmation', $order->status);

            // Assert cart is now empty
            $this->assertSame(0, $cartService->getCount());

            // Visit success page
            $successResponse = $this->get("http://{$this->domain}/checkout/success/{$order->reference}");
            $successResponse->assertStatus(200);
            $successResponse->assertSee($order->reference);
            $successResponse->assertSee('محمد الشناوي');
            $successResponse->assertSee('شكراً لتسوقك معنا!');
        });
    }
}
