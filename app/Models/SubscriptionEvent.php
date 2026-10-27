<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $subscription_id
 * @property string $tenant_id
 * @property string $event
 * @property string|null $actor_type
 * @property string|null $actor_id
 * @property array<string, mixed>|null $metadata
 * @property Carbon $created_at
 * @property-read Subscription $subscription
 * @property-read Tenant $tenant
 */
class SubscriptionEvent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'subscription_id',
        'tenant_id',
        'event',
        'actor_type',
        'actor_id',
        'metadata',
        'created_at',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<Subscription, $this>
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }
}
