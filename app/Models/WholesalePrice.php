<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Money;
use App\Support\Tenancy\Concerns\BelongsToTenant;
use DomainException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Lunar\Models\ProductVariant;

/**
 * @property int $id
 * @property string $tenant_id
 * @property int $product_variant_id
 * @property int|null $supplier_id
 * @property int|null $wholesale_price_minor
 * @property int|null $min_price_minor
 * @property int|null $max_price_minor
 * @property int|null $min_commission_minor
 * @property int|null $max_commission_minor
 * @property int|null $compare_at_price_minor
 * @property bool $is_digital
 * @property string|null $digital_file_path
 * @property string $status
 * @property string|null $pause_reason
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read ProductVariant $variant
 * @property-read Supplier|null $supplier
 */
class WholesalePrice extends Model
{
    use BelongsToTenant;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_PAUSED = 'paused';

    public const STATUS_ARCHIVED = 'archived';

    protected $fillable = [
        'tenant_id',
        'product_variant_id',
        'supplier_id',
        'wholesale_price_minor',
        'min_price_minor',
        'max_price_minor',
        'min_commission_minor',
        'max_commission_minor',
        'compare_at_price_minor',
        'is_digital',
        'digital_file_path',
        'status',
        'pause_reason',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'product_variant_id' => 'integer',
        'supplier_id' => 'integer',
        'wholesale_price_minor' => 'integer',
        'min_price_minor' => 'integer',
        'max_price_minor' => 'integer',
        'min_commission_minor' => 'integer',
        'max_commission_minor' => 'integer',
        'compare_at_price_minor' => 'integer',
        'is_digital' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $model) {
            // Default min & max if not explicitly provided
            if ($model->min_price_minor === null && $model->wholesale_price_minor !== null) {
                $model->min_price_minor = $model->wholesale_price_minor;
            }
            if ($model->max_price_minor === null && $model->min_price_minor !== null) {
                $model->max_price_minor = $model->min_price_minor;
            }

            // Invariant: max >= min >= wholesale
            if ($model->max_price_minor !== null && $model->min_price_minor !== null && $model->max_price_minor < $model->min_price_minor) {
                throw new DomainException(__('الحد الأقصى لسعر البيع (:max) يجب أن يكون أكبر من أو يساوي الحد الأدنى (:min).', [
                    'max' => Money::fromMinor((int) $model->max_price_minor)->format('ar'),
                    'min' => Money::fromMinor((int) $model->min_price_minor)->format('ar'),
                ]));
            }

            if ($model->min_price_minor !== null && $model->wholesale_price_minor !== null && $model->min_price_minor < $model->wholesale_price_minor) {
                throw new DomainException(__('الحد الأدنى لسعر البيع (:min) يجب أن يكون أكبر من أو يساوي سعر الجملة (:wholesale).', [
                    'min' => Money::fromMinor((int) $model->min_price_minor)->format('ar'),
                    'wholesale' => Money::fromMinor((int) $model->wholesale_price_minor)->format('ar'),
                ]));
            }

            // Pause reason requirement
            if ($model->status === self::STATUS_PAUSED && empty(trim((string) $model->pause_reason))) {
                throw new DomainException(__('يجب تحديد سبب إيقاف المنتج عند تغيير حالته إلى موقوف مؤقتًا.'));
            }
        });
    }

    /**
     * @return BelongsTo<ProductVariant, $this>
     */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    /**
     * @return BelongsTo<Supplier, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    public function wholesalePriceMoney(): Money
    {
        return Money::fromMinor((int) ($this->wholesale_price_minor ?? 0));
    }

    public function minPriceMoney(): Money
    {
        return Money::fromMinor((int) ($this->min_price_minor ?? $this->wholesale_price_minor ?? 0));
    }

    public function maxPriceMoney(): Money
    {
        return Money::fromMinor((int) ($this->max_price_minor ?? $this->min_price_minor ?? $this->wholesale_price_minor ?? 0));
    }

    public function compareAtPriceMoney(): ?Money
    {
        return $this->compare_at_price_minor !== null ? Money::fromMinor((int) $this->compare_at_price_minor) : null;
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isPaused(): bool
    {
        return $this->status === self::STATUS_PAUSED;
    }

    public function isArchived(): bool
    {
        return $this->status === self::STATUS_ARCHIVED;
    }
}
