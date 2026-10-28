<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $tenant_id
 * @property int $affiliate_id
 * @property Carbon $calculated_date
 * @property int $quality_score
 * @property int $delivered_count
 * @property int $returned_count
 * @property int $rejected_count
 * @property int $delivery_rate
 * @property int $daily_order_limit
 * @property int|null $affiliate_level_id
 * @property array<string, mixed>|null $reasons
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Affiliate $affiliate
 * @property-read AffiliateLevel|null $level
 */
class AffiliateQualityLog extends Model
{
    use BelongsToTenant;

    protected $table = 'affiliate_quality_logs';

    protected $fillable = [
        'tenant_id',
        'affiliate_id',
        'calculated_date',
        'quality_score',
        'delivered_count',
        'returned_count',
        'rejected_count',
        'delivery_rate',
        'daily_order_limit',
        'affiliate_level_id',
        'reasons',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'calculated_date' => 'date',
            'quality_score' => 'integer',
            'delivered_count' => 'integer',
            'returned_count' => 'integer',
            'rejected_count' => 'integer',
            'delivery_rate' => 'integer',
            'daily_order_limit' => 'integer',
            'reasons' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Affiliate, $this>
     */
    public function affiliate(): BelongsTo
    {
        return $this->belongsTo(Affiliate::class);
    }

    /**
     * @return BelongsTo<AffiliateLevel, $this>
     */
    public function level(): BelongsTo
    {
        return $this->belongsTo(AffiliateLevel::class, 'affiliate_level_id');
    }
}
