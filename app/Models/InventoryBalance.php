<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Tenancy\Concerns\BelongsToTenant;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Lunar\Models\ProductVariant;

/**
 * @property int $id
 * @property string $tenant_id
 * @property int $warehouse_id
 * @property int $product_variant_id
 * @property int $on_hand
 * @property int $reserved
 * @property int $low_stock_threshold
 * @property-read int $available
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Warehouse|null $warehouse
 * @property-read ProductVariant|null $variant
 */
class InventoryBalance extends Model
{
    use BelongsToTenant;

    protected $table = 'inventory_balances';

    protected $fillable = [
        'tenant_id',
        'warehouse_id',
        'product_variant_id',
        'on_hand',
        'reserved',
        'low_stock_threshold',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'warehouse_id' => 'integer',
            'product_variant_id' => 'integer',
            'on_hand' => 'integer',
            'reserved' => 'integer',
            'low_stock_threshold' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $balance): void {
            if ($balance->reserved < 0) {
                throw new DomainException('Reserved stock cannot be negative.');
            }

            if ($balance->on_hand < 0) {
                throw new DomainException('On-hand stock cannot be negative.');
            }

            if ($balance->reserved > $balance->on_hand) {
                throw new DomainException(sprintf(
                    'Reserved stock (%d) cannot exceed on-hand stock (%d).',
                    $balance->reserved,
                    $balance->on_hand
                ));
            }
        });
    }

    /**
     * Get available stock (on_hand - reserved).
     */
    public function getAvailableAttribute(): int
    {
        return max(0, $this->on_hand - $this->reserved);
    }

    /**
     * Check if available stock is at or below the low stock threshold.
     */
    public function isLowStock(): bool
    {
        return $this->available <= $this->low_stock_threshold;
    }

    /**
     * @return BelongsTo<Warehouse, $this>
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    /**
     * @return BelongsTo<ProductVariant, $this>
     */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    /**
     * @return HasMany<InventoryMovement, $this>
     */
    public function movements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class, 'product_variant_id', 'product_variant_id')
            ->where('warehouse_id', $this->warehouse_id);
    }

    /**
     * Scope for a specific variant.
     *
     * @param  Builder<InventoryBalance>  $query
     */
    public function scopeForVariant(Builder $query, int $variantId): void
    {
        $query->where('product_variant_id', $variantId);
    }

    /**
     * Scope for low stock balances.
     *
     * @param  Builder<InventoryBalance>  $query
     */
    public function scopeLowStock(Builder $query): void
    {
        $query->whereRaw('(on_hand - reserved) <= low_stock_threshold');
    }
}
