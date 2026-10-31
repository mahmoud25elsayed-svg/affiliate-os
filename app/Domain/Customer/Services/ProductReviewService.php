<?php

declare(strict_types=1);

namespace App\Domain\Customer\Services;

use App\Models\Customer;
use App\Models\ProductReview;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;
use Lunar\Models\Order;
use Lunar\Models\OrderLine;
use Lunar\Models\ProductVariant;

class ProductReviewService
{
    /**
     * Determine if customer has purchased the product.
     */
    public function canReviewProduct(Customer $customer, int $productId): bool
    {
        $variantIds = ProductVariant::query()
            ->where('product_id', $productId)
            ->pluck('id')
            ->toArray();

        if (empty($variantIds)) {
            return false;
        }

        $orderIds = Order::query()
            ->where('storefront_customer_id', $customer->id)
            ->pluck('id')
            ->toArray();

        if (empty($orderIds)) {
            // Also check by customer phone hash if order was placed prior to account registration
            if ($customer->phone_hash) {
                $orderIds = Order::query()
                    ->where('customer_phone_hash', $customer->phone_hash)
                    ->pluck('id')
                    ->toArray();
            }
        }

        if (empty($orderIds)) {
            return false;
        }

        return OrderLine::query()
            ->whereIn('order_id', $orderIds)
            ->whereIn('purchasable_id', $variantIds)
            ->exists();
    }

    /**
     * Submit a review for a purchased product (pending merchant moderation).
     *
     * @throws ValidationException
     */
    public function submitReview(
        Customer $customer,
        int $productId,
        int $rating,
        string $comment,
        ?string $title = null
    ): ProductReview {
        if ($rating < 1 || $rating > 5) {
            throw ValidationException::withMessages([
                'rating' => [__('يجب أن يكون التقييم بين 1 و 5 نجوم.')],
            ]);
        }

        if (trim($comment) === '') {
            throw ValidationException::withMessages([
                'comment' => [__('يرجى كتابة نص المراجعة.')],
            ]);
        }

        if (! $this->canReviewProduct($customer, $productId)) {
            throw ValidationException::withMessages([
                'rating' => [__('التقييم متاح حصرياً للمشترين الفعليين لهذا المنتج.')],
            ]);
        }

        // Find the matching order
        $variantIds = ProductVariant::query()
            ->where('product_id', $productId)
            ->pluck('id')
            ->toArray();

        /** @var OrderLine|null $orderLine */
        $orderLine = OrderLine::query()
            ->whereHas('order', function ($query) use ($customer) {
                $query->where('storefront_customer_id', $customer->id)
                    ->orWhere('customer_phone_hash', $customer->phone_hash);
            })
            ->whereIn('purchasable_id', $variantIds)
            ->first();

        $tenantId = function_exists('tenancy') && tenancy()->initialized ? (string) tenant('id') : 'default';

        /** @var ProductReview $review */
        $review = ProductReview::create([
            'tenant_id' => $tenantId,
            'customer_id' => $customer->id,
            'product_id' => $productId,
            'order_id' => $orderLine?->order_id,
            'rating' => $rating,
            'title' => $title,
            'comment' => trim($comment),
            'status' => 'pending',
            'is_verified_purchase' => true,
        ]);

        return $review;
    }

    public function approve(ProductReview $review): void
    {
        $review->update([
            'status' => 'approved',
            'rejection_reason' => null,
        ]);
    }

    public function reject(ProductReview $review, string $reason): void
    {
        $review->update([
            'status' => 'rejected',
            'rejection_reason' => $reason,
        ]);
    }

    /**
     * Get approved reviews summary for a product.
     *
     * @return array{
     *     average_rating: float,
     *     reviews_count: int,
     *     rating_breakdown: array<int, int>,
     *     reviews: Collection<int, ProductReview>
     * }
     */
    public function getProductSummary(int $productId): array
    {
        /** @var Collection<int, ProductReview> $reviews */
        $reviews = ProductReview::query()
            ->approved()
            ->where('product_id', $productId)
            ->with('customer')
            ->latest()
            ->get();

        $count = $reviews->count();
        $avg = $count > 0 ? round((float) $reviews->avg('rating'), 1) : 0.0;

        $breakdown = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0];
        foreach ($reviews as $rev) {
            $star = (int) $rev->rating;
            if (isset($breakdown[$star])) {
                $breakdown[$star]++;
            }
        }

        return [
            'average_rating' => (float) $avg,
            'reviews_count' => $count,
            'rating_breakdown' => $breakdown,
            'reviews' => $reviews,
        ];
    }
}
