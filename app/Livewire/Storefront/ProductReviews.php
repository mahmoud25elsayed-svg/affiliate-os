<?php

declare(strict_types=1);

namespace App\Livewire\Storefront;

use App\Domain\Customer\Services\ProductReviewService;
use App\Models\Customer;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class ProductReviews extends Component
{
    public int $productId;

    public int $rating = 5;

    public string $title = '';

    public string $comment = '';

    public ?string $successMessage = null;

    public ?string $errorMessage = null;

    public function mount(int $productId): void
    {
        $this->productId = $productId;
    }

    public function submitReview(ProductReviewService $reviewService): void
    {
        $this->successMessage = null;
        $this->errorMessage = null;

        /** @var Customer|null $customer */
        $customer = Auth::guard('customer')->user();
        if (! $customer) {
            $this->errorMessage = __('يرجى تسجيل الدخول لتتمكن من تقييم المنتج.');

            return;
        }

        try {
            $reviewService->submitReview(
                $customer,
                $this->productId,
                $this->rating,
                $this->comment,
                $this->title !== '' ? $this->title : null
            );

            $this->reset(['rating', 'title', 'comment']);
            $this->rating = 5;
            $this->successMessage = __('شكراً لتقييمك! تم إرسال مراجعتك بنجاح وستظهر فور اعتمادها من إدارة المتجر.');
        } catch (ValidationException $e) {
            $err = $e->validator->errors()->first();
            $this->errorMessage = $err !== '' ? $err : __('تعذر إرسال التقييم.');
        }
    }

    public function render(ProductReviewService $reviewService): View
    {
        $summary = $reviewService->getProductSummary($this->productId);

        /** @var Customer|null $customer */
        $customer = Auth::guard('customer')->user();
        $canReview = $customer ? $reviewService->canReviewProduct($customer, $this->productId) : false;

        return view('livewire.storefront.product-reviews', [
            'summary' => $summary,
            'canReview' => $canReview,
            'isLoggedIn' => $customer !== null,
        ]);
    }
}
