<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $tenant_id
 * @property string $name_ar
 * @property string|null $name_en
 * @property string $type
 * @property array<string, mixed> $conditions
 * @property string $discount_type
 * @property int $discount_value
 * @property Carbon|null $starts_at
 * @property Carbon|null $ends_at
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property string $name
 * @property string $bundle_type
 * @property int|null $primary_product_id
 * @property int|null $reward_product_id
 * @property int $required_quantity
 * @property int $reward_quantity
 * @property int $reward_discount_percent
 */
class ProductBundle extends Model
{
    use BelongsToTenant;

    public const TYPE_BUY_X_GET_Y = 'buy_x_get_y';

    public const TYPE_COMBO_PACK = 'combo_pack';

    protected $table = 'product_bundles';

    protected $fillable = [
        'tenant_id',
        'name_ar',
        'name_en',
        'name',
        'type',
        'bundle_type',
        'conditions',
        'discount_type',
        'discount_value',
        'primary_product_id',
        'reward_product_id',
        'required_quantity',
        'reward_quantity',
        'reward_discount_percent',
        'starts_at',
        'ends_at',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'conditions' => 'array',
            'discount_value' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $bundle): void {
            if (empty($bundle->name_ar) && ! empty($bundle->name_en)) {
                $bundle->name_ar = $bundle->name_en;
            }
            if (empty($bundle->conditions)) {
                $bundle->conditions = [];
            }
        });
    }

    public function getNameAttribute(): string
    {
        return $this->name_ar ?? '';
    }

    public function setNameAttribute(string $value): void
    {
        $this->attributes['name_ar'] = $value;
    }

    public function getBundleTypeAttribute(): string
    {
        return $this->type ?? self::TYPE_BUY_X_GET_Y;
    }

    public function setBundleTypeAttribute(string $value): void
    {
        $this->attributes['type'] = $value;
    }

    public function getPrimaryProductIdAttribute(): ?int
    {
        return isset($this->conditions['buy_product_id']) ? (int) $this->conditions['buy_product_id'] : null;
    }

    public function setPrimaryProductIdAttribute(?int $value): void
    {
        $conds = $this->conditions ?? [];
        $conds['buy_product_id'] = $value;
        $this->conditions = $conds;
    }

    public function getRequiredQuantityAttribute(): int
    {
        return (int) ($this->conditions['buy_quantity'] ?? 1);
    }

    public function setRequiredQuantityAttribute(int $value): void
    {
        $conds = $this->conditions ?? [];
        $conds['buy_quantity'] = $value;
        $this->conditions = $conds;
    }

    public function getRewardProductIdAttribute(): ?int
    {
        return isset($this->conditions['get_product_id']) ? (int) $this->conditions['get_product_id'] : null;
    }

    public function setRewardProductIdAttribute(?int $value): void
    {
        $conds = $this->conditions ?? [];
        $conds['get_product_id'] = $value;
        $this->conditions = $conds;
    }

    public function getRewardQuantityAttribute(): int
    {
        return (int) ($this->conditions['get_quantity'] ?? 1);
    }

    public function setRewardQuantityAttribute(int $value): void
    {
        $conds = $this->conditions ?? [];
        $conds['get_quantity'] = $value;
        $this->conditions = $conds;
    }

    public function getRewardDiscountPercentAttribute(): int
    {
        return (int) ($this->discount_value ?? 100);
    }

    public function setRewardDiscountPercentAttribute(int $value): void
    {
        $this->attributes['discount_value'] = $value;
    }

    /**
     * Scope for currently active bundles.
     *
     * @param  Builder<ProductBundle>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $now = now();
        $query->where('is_active', true)
            ->where(function (Builder $q) use ($now) {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now);
            })
            ->where(function (Builder $q) use ($now) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>', $now);
            });
    }

    /**
     * Check if bundle applies to the cart items.
     *
     * @param  array<int, int>  $cartQuantities  Key: product_variant_id, Value: quantity
     * @return array{applies: bool, free_variant_id?: int, free_quantity?: int, discount_minor?: int}
     */
    public function evaluate(array $cartQuantities): array
    {
        if ($this->type === self::TYPE_BUY_X_GET_Y) {
            $buyVariantId = (int) ($this->conditions['buy_variant_id'] ?? 0);
            $buyQty = (int) ($this->conditions['buy_quantity'] ?? 1);
            $getVariantId = (int) ($this->conditions['get_variant_id'] ?? $buyVariantId);
            $getQty = (int) ($this->conditions['get_quantity'] ?? 1);

            $inCartBuy = $cartQuantities[$buyVariantId] ?? 0;

            if ($inCartBuy >= $buyQty) {
                $multiplier = (int) floor($inCartBuy / $buyQty);

                return [
                    'applies' => true,
                    'free_variant_id' => $getVariantId,
                    'free_quantity' => $multiplier * $getQty,
                ];
            }
        }

        return ['applies' => false];
    }
}
