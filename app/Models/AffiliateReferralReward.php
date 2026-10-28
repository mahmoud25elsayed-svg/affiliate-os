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
 * @property int $referrer_id
 * @property int $referred_id
 * @property int $threshold_orders
 * @property int $reward_amount_minor
 * @property int|null $journal_entry_id
 * @property Carbon $awarded_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Affiliate $referrer
 * @property-read Affiliate $referred
 */
class AffiliateReferralReward extends Model
{
    use BelongsToTenant;

    protected $table = 'affiliate_referral_rewards';

    protected $fillable = [
        'tenant_id',
        'referrer_id',
        'referred_id',
        'threshold_orders',
        'reward_amount_minor',
        'journal_entry_id',
        'awarded_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'threshold_orders' => 'integer',
            'reward_amount_minor' => 'integer',
            'journal_entry_id' => 'integer',
            'awarded_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Affiliate, $this>
     */
    public function referrer(): BelongsTo
    {
        return $this->belongsTo(Affiliate::class, 'referrer_id');
    }

    /**
     * @return BelongsTo<Affiliate, $this>
     */
    public function referred(): BelongsTo
    {
        return $this->belongsTo(Affiliate::class, 'referred_id');
    }
}
