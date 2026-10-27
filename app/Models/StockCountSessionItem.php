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
 * @property int $session_id
 * @property int $product_variant_id
 * @property int $system_on_hand
 * @property int $counted_on_hand
 * @property int $discrepancy
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read StockCountSession|null $session
 * @property-read ProductVariant|null $variant
 */
class StockCountSessionItem extends Model
{
    use BelongsToTenant;

    protected $table = 'stock_count_session_items';

    protected $fillable = [
        'tenant_id',
        'session_id',
        'product_variant_id',
        'system_on_hand',
        'counted_on_hand',
        'discrepancy',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'session_id' => 'integer',
            'product_variant_id' => 'integer',
            'system_on_hand' => 'integer',
            'counted_on_hand' => 'integer',
            'discrepancy' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<StockCountSession, $this>
     */
    public function session(): BelongsTo
    {
        return $this->belongsTo(StockCountSession::class, 'session_id');
    }

    /**
     * @return BelongsTo<ProductVariant, $this>
     */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }
}
