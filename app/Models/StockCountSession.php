<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $tenant_id
 * @property int $warehouse_id
 * @property string $reference
 * @property string $status
 * @property int|null $creator_id
 * @property int|null $approver_id
 * @property string|null $notes
 * @property Carbon|null $approved_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Warehouse|null $warehouse
 * @property-read Collection<int, StockCountSessionItem> $items
 */
class StockCountSession extends Model
{
    use BelongsToTenant;

    protected $table = 'stock_count_sessions';

    protected $fillable = [
        'tenant_id',
        'warehouse_id',
        'reference',
        'status',
        'creator_id',
        'approver_id',
        'notes',
        'approved_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'warehouse_id' => 'integer',
            'creator_id' => 'integer',
            'approver_id' => 'integer',
            'approved_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Warehouse, $this>
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    /**
     * @return HasMany<StockCountSessionItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(StockCountSessionItem::class, 'session_id');
    }
}
