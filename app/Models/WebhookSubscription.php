<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $tenant_id
 * @property string $url
 * @property string $secret
 * @property array<string> $events
 * @property bool $is_active
 * @property int $failure_count
 * @property Carbon|null $last_triggered_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, WebhookDelivery> $deliveries
 */
class WebhookSubscription extends Model
{
    use BelongsToTenant;

    public const EVENT_ORDER_CREATED = 'order.created';

    public const EVENT_ORDER_STATUS_UPDATED = 'order.status_updated';

    public const EVENT_ORDER_DELIVERED = 'order.delivered';

    public const EVENT_INVENTORY_LOW = 'inventory.low';

    public const EVENT_PAYOUT_APPROVED = 'payout.approved';

    public const EVENT_PAYOUT_EXECUTED = 'payout.executed';

    protected $guarded = [];

    protected $casts = [
        'events' => 'array',
        'is_active' => 'boolean',
        'failure_count' => 'integer',
        'last_triggered_at' => 'datetime',
    ];

    /**
     * @return HasMany<WebhookDelivery, $this>
     */
    public function deliveries(): HasMany
    {
        return $this->hasMany(WebhookDelivery::class, 'webhook_subscription_id');
    }

    public function subscribesTo(string $event): bool
    {
        if (in_array('*', $this->events, true)) {
            return true;
        }

        return in_array($event, $this->events, true);
    }

    public static function generateSecret(): string
    {
        return 'whsec_'.Str::random(32);
    }

    public function recordFailure(): void
    {
        $this->increment('failure_count');
        if ($this->failure_count >= 10) {
            $this->update(['is_active' => false]);
        }
    }

    public function recordSuccess(): void
    {
        $this->update([
            'failure_count' => 0,
            'last_triggered_at' => now(),
        ]);
    }
}
