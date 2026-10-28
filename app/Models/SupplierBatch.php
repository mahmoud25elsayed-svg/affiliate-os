<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Lunar\Models\ProductVariant;

/**
 * @property int $id
 * @property string $tenant_id
 * @property string $batch_number
 * @property int $supplier_id
 * @property int $product_variant_id
 * @property int $quantity_supplied
 * @property int $unit_cost_minor
 * @property int $total_cost_minor
 * @property string $status
 * @property Carbon|null $received_at
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Supplier $supplier
 * @property-read ProductVariant $variant
 */
class SupplierBatch extends Model
{
    use BelongsToTenant;

    public const STATUS_PENDING = 'pending';

    public const STATUS_RECEIVED = 'received';

    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'tenant_id',
        'batch_number',
        'supplier_id',
        'product_variant_id',
        'quantity_supplied',
        'unit_cost_minor',
        'total_cost_minor',
        'status',
        'received_at',
        'notes',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'supplier_id' => 'integer',
        'product_variant_id' => 'integer',
        'quantity_supplied' => 'integer',
        'unit_cost_minor' => 'integer',
        'total_cost_minor' => 'integer',
        'received_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<Supplier, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * @return BelongsTo<ProductVariant, $this>
     */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }
}
