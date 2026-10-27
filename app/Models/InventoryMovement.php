<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;
use Lunar\Models\ProductVariant;

/**
 * @property int $id
 * @property string $tenant_id
 * @property int $warehouse_id
 * @property int $product_variant_id
 * @property int $quantity_delta
 * @property int $reserved_delta
 * @property string $reason
 * @property string|null $reference_type
 * @property string|null $reference_id
 * @property string|null $reference_key
 * @property string $actor_type
 * @property string|null $actor_id
 * @property string|null $notes
 * @property Carbon $created_at
 * @property-read Warehouse|null $warehouse
 * @property-read ProductVariant|null $variant
 */
class InventoryMovement extends Model
{
    use BelongsToTenant;

    public const UPDATED_AT = null;

    protected $table = 'inventory_movements';

    protected $fillable = [
        'tenant_id',
        'warehouse_id',
        'product_variant_id',
        'quantity_delta',
        'reserved_delta',
        'reason',
        'reference_type',
        'reference_id',
        'reference_key',
        'actor_type',
        'actor_id',
        'notes',
        'created_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'warehouse_id' => 'integer',
            'product_variant_id' => 'integer',
            'quantity_delta' => 'integer',
            'reserved_delta' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new LogicException('Inventory movements are append-only audit records and cannot be updated.');
        });

        static::deleting(function (): void {
            throw new LogicException('Inventory movements are append-only audit records and cannot be deleted.');
        });
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
}
