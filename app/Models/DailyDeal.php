<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Lunar\Models\Product;
use Lunar\Models\ProductVariant;

/**
 * @property int $id
 * @property string $tenant_id
 * @property int $product_id
 * @property int|null $product_variant_id
 * @property string $title_ar
 * @property string $discount_type
 * @property int $discount_value
 * @property Carbon $deal_date
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Product|null $product
 * @property-read ProductVariant|null $variant
 */
class DailyDeal extends Model
{
    use BelongsToTenant;

    protected $table = 'daily_deals';

    protected $fillable = [
        'tenant_id',
        'product_id',
        'product_variant_id',
        'title_ar',
        'discount_type',
        'discount_value',
        'deal_date',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'product_id' => 'integer',
            'product_variant_id' => 'integer',
            'discount_value' => 'integer',
            'deal_date' => 'date',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $deal): void {
            if (empty($deal->title_ar)) {
                /** @var Product|null $prod */
                $prod = Product::find($deal->product_id);
                $deal->title_ar = $prod ? (string) ($prod->translateAttribute('name') ?? 'صفقة اليوم') : 'صفقة اليوم';
            }
        });
    }

    public function getBannerTitleAttribute(): string
    {
        return $this->title_ar ?? '';
    }

    public function setBannerTitleAttribute(?string $value): void
    {
        if ($value !== null && $value !== '') {
            $this->attributes['title_ar'] = $value;
        }
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
     * Scope for today's active deal.
     *
     * @param  Builder<DailyDeal>  $query
     */
    public function scopeToday(Builder $query): void
    {
        $query->where('is_active', true)
            ->whereDate('deal_date', Carbon::today());
    }

    /**
     * Calculate discounted price in minor units for a given original price.
     */
    public function calculateDiscountedPrice(int $originalPriceMinor): int
    {
        if ($this->discount_type === 'percentage') {
            $discountMinor = (int) round(($originalPriceMinor * $this->discount_value) / 100);

            return max(0, $originalPriceMinor - $discountMinor);
        }

        return max(0, $originalPriceMinor - $this->discount_value);
    }
}
