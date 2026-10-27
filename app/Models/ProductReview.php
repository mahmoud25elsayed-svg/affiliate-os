<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Lunar\Models\Order;
use Lunar\Models\Product;

/**
 * @property int $id
 * @property string $tenant_id
 * @property int $customer_id
 * @property int $product_id
 * @property int|null $order_id
 * @property int $rating
 * @property string|null $title
 * @property string $comment
 * @property string $status
 * @property string|null $rejection_reason
 * @property bool $is_verified_purchase
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class ProductReview extends Model
{
    use BelongsToTenant;

    protected $table = 'product_reviews';

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'is_verified_purchase' => 'boolean',
            'customer_id' => 'integer',
            'product_id' => 'integer',
            'order_id' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    /**
     * @param  Builder<ProductReview>  $query
     * @return Builder<ProductReview>
     */
    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', 'approved');
    }

    /**
     * @param  Builder<ProductReview>  $query
     * @return Builder<ProductReview>
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }
}
