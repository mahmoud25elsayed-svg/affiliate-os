<?php

declare(strict_types=1);

namespace Tests\Feature\Customer;

use App\Domain\Catalog\Services\ProductService;
use App\Domain\Customer\Services\ProductReviewService;
use App\Livewire\Storefront\ProductReviews;
use App\Models\Customer;
use App\Models\ProductReview;
use App\Models\Tenant;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Lunar\Base\ValueObjects\Cart\TaxBreakdown;
use Lunar\Models\Channel;
use Lunar\Models\Order;
use Lunar\Models\OrderLine;
use Lunar\Models\Product;
use Tests\TestCase;

class ProductReviewWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected Product $product;

    protected Customer $buyer;

    protected Customer $nonBuyer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PlanSeeder::class);
        $this->tenant = $this->ensureTenantExists('revstore');

        $this->tenant->run(function () {
            $productService = app(ProductService::class);
            $this->product = $productService->createProduct([
                'name_ar' => 'سماعات لاسلكية بلوتوث',
                'sku' => 'EARBUDS-BT',
                'wholesale_price' => 30000,
                'min_price' => 40000,
                'max_price' => 60000,
                'retail_price' => 45000,
                'status' => 'active',
            ]);

            $this->buyer = Customer::create([
                'tenant_id' => $this->tenant->id,
                'first_name' => 'عمر',
                'last_name' => 'فاروق',
                'email' => 'omar@example.com',
                'phone_e164' => '+201099998888',
                'phone_hash' => hash('sha256', '+201099998888'),
                'is_active' => true,
            ]);

            $this->nonBuyer = Customer::create([
                'tenant_id' => $this->tenant->id,
                'first_name' => 'يوسف',
                'last_name' => 'كريم',
                'email' => 'youssef@example.com',
                'phone_e164' => '+201077776666',
                'phone_hash' => hash('sha256', '+201077776666'),
                'is_active' => true,
            ]);

            // Create an order for buyer containing product's variant
            $variant = $this->product->variants->firstOrFail();
            $channel = Channel::first() ?? Channel::create(['name' => 'Default', 'handle' => 'default', 'default' => true]);

            $order = Order::create([
                'storefront_customer_id' => $this->buyer->id,
                'channel_id' => $channel->id,
                'status' => 'confirmed',
                'reference' => 'ORD-26-BUYER1',
                'customer_reference' => 'ORD-26-BUYER1',
                'sub_total' => 45000,
                'discount_total' => 0,
                'shipping_total' => 4000,
                'tax_breakdown' => new TaxBreakdown,
                'tax_total' => 0,
                'total' => 49000,
                'currency_code' => 'EGP',
                'compare_currency_code' => 'EGP',
            ]);

            OrderLine::create([
                'order_id' => $order->id,
                'purchasable_type' => 'Lunar\Models\ProductVariant',
                'purchasable_id' => $variant->id,
                'type' => 'physical',
                'description' => 'سماعات لاسلكية بلوتوث',
                'identifier' => 'EARBUDS-BT',
                'unit_price' => 45000,
                'unit_quantity' => 1,
                'quantity' => 1,
                'sub_total' => 45000,
                'discount_total' => 0,
                'tax_breakdown' => new TaxBreakdown,
                'tax_total' => 0,
                'total' => 45000,
            ]);
        });
    }

    public function test_non_buyer_cannot_submit_review(): void
    {
        $this->tenant->run(function () {
            $service = app(ProductReviewService::class);

            $this->assertFalse($service->canReviewProduct($this->nonBuyer, $this->product->id));

            $this->expectException(ValidationException::class);
            $service->submitReview($this->nonBuyer, $this->product->id, 5, 'منتج ممتاز لكن لم أشتريه!');
        });
    }

    public function test_buyer_can_submit_review_and_it_starts_as_pending(): void
    {
        $this->tenant->run(function () {
            $service = app(ProductReviewService::class);

            $this->assertTrue($service->canReviewProduct($this->buyer, $this->product->id));

            $review = $service->submitReview(
                $this->buyer,
                $this->product->id,
                5,
                'صوت نقي وبطارية تدوم طويلاً، أنصح بشرائها!',
                'تجربة رائعة'
            );

            $this->assertInstanceOf(ProductReview::class, $review);
            $this->assertSame('pending', $review->status);
            $this->assertTrue($review->is_verified_purchase);
            $this->assertSame(5, $review->rating);

            // Storefront summary does NOT count pending reviews yet
            $summary = $service->getProductSummary($this->product->id);
            $this->assertSame(0, $summary['reviews_count']);
            $this->assertSame(0.0, $summary['average_rating']);
        });
    }

    public function test_merchant_can_approve_or_reject_review(): void
    {
        $this->tenant->run(function () {
            $service = app(ProductReviewService::class);

            $review = $service->submitReview(
                $this->buyer,
                $this->product->id,
                4,
                'جيدة جداً مقابل السعر'
            );

            // Approve review
            $service->approve($review);
            $this->assertSame('approved', $review->fresh()->status);

            // Now summary reflects approved review
            $summary = $service->getProductSummary($this->product->id);
            $this->assertSame(1, $summary['reviews_count']);
            $this->assertSame(4.0, $summary['average_rating']);

            // Reject review with reason
            $service->reject($review, 'تحتوي على ألفاظ غير لائقة');
            $this->assertSame('rejected', $review->fresh()->status);
            $this->assertSame('تحتوي على ألفاظ غير لائقة', $review->fresh()->rejection_reason);

            // No longer in summary
            $summaryAfterReject = $service->getProductSummary($this->product->id);
            $this->assertSame(0, $summaryAfterReject['reviews_count']);
        });
    }

    public function test_livewire_product_reviews_interaction(): void
    {
        $this->tenant->run(function () {
            Auth::guard('customer')->login($this->buyer);

            Livewire::test(ProductReviews::class, ['productId' => $this->product->id])
                ->set('rating', 5)
                ->set('title', 'ممتازة جداً')
                ->set('comment', 'سماعة مريحة وعزل الصوت قوي')
                ->call('submitReview')
                ->assertSee('شكراً لتقييمك');

            $review = ProductReview::where('product_id', $this->product->id)->firstOrFail();
            $this->assertSame('pending', $review->status);

            // Approve it
            $review->update(['status' => 'approved']);

            // Rerender shows approved review
            Livewire::test(ProductReviews::class, ['productId' => $this->product->id])
                ->assertSee('سماعة مريحة وعزل الصوت قوي')
                ->assertSee('مشتري مؤكد ✓');
        });
    }
}
