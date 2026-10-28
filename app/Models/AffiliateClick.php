<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AffiliateClick extends Model
{
    use BelongsToTenant;

    public const UPDATED_AT = null;

    protected $table = 'affiliate_clicks';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'tracking_link_id',
        'affiliate_id',
        'ip_hash',
        'ua_hash',
        'referer',
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'utm_term',
        'utm_content',
        'landing_url',
    ];

    /**
     * @return BelongsTo<TrackingLink, $this>
     */
    public function trackingLink(): BelongsTo
    {
        return $this->belongsTo(TrackingLink::class, 'tracking_link_id');
    }

    /**
     * @return BelongsTo<Affiliate, $this>
     */
    public function affiliate(): BelongsTo
    {
        return $this->belongsTo(Affiliate::class);
    }
}
