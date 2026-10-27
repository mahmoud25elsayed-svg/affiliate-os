<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Lunar\Models\Product;
use Lunar\Models\ProductVariant;

/**
 * @property int $id
 * @property string $tenant_id
 * @property int $flash_sale_id
 * @property int $product_id
 * @property int $product_variant_id
 * @property string $discount_type
 * @property int $discount_value
 * @property int $flash_price_minor
 * @property int $allocated_quantity
 * @property int $sold_quantity
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read FlashSale|null $flashSale
 * @property-read Product|null $product
 * @property-read ProductVariant|null $variant
 */
class FlashSaleItem extends Model
{
    use BelongsToTenant;

    protected $table = 'flash_sale_items';

    protected $fillable = [
        'tenant_id',
        'flash_sale_id',
        'product_id',
        'product_variant_id',
        'discount_type',
        'discount_value',
        'flash_price_minor',
        'allocated_quantity',
        'sold_quantity',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'flash_sale_id' => 'integer',
            'product_id' => 'integer',
            'product_variant_id' => 'integer',
            'discount_value' => 'integer',
            'flash_price_minor' => 'integer',
            'allocated_quantity' => 'integer',
            'sold_quantity' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $item): void {
            if (empty($item->product_id) && ! empty($item->product_variant_id)) {
                /** @var ProductVariant|null $variant */
                $variant = ProductVariant::find($item->product_variant_id);
                if ($variant) {
                    $item->product_id = (int) $variant->product_id;
                }
            }
            if (! isset($item->discount_value)) {
                $item->discount_value = 0;
            }
        });
    }

    /**
     * @return BelongsTo<FlashSale, $this>
     */
    public function flashSale(): BelongsTo
    {
        return $this->belongsTo(FlashSale::class, 'flash_sale_id');
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    /**
     * @return BelongsTo<ProductVariant, $this>
     */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    /**
     * Check if flash sale stock is still available.
     */
    public function isAvailable(): bool
    {
        if ($this->allocated_quantity <= 0) {
            return false;
        }

        return $this->sold_quantity < $this->allocated_quantity;
    }

    /**
     * Remaining flash sale units.
     */
    public function remainingStock(): int
    {
        return max(0, $this->allocated_quantity - $this->sold_quantity);
    }

    /**
     * Increment sold quantity safely.
     */
    public function recordSale(int $quantity = 1): void
    {
        $this->increment('sold_quantity', $quantity);
    }
}
