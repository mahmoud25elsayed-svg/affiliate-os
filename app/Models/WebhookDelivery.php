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
 * @property int|null $webhook_subscription_id
 * @property string $event
 * @property array<string, mixed> $payload
 * @property int|null $response_status
 * @property string|null $response_body
 * @property int $attempt
 * @property string $status
 * @property Carbon|null $delivered_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read WebhookSubscription|null $subscription
 */
class WebhookDelivery extends Model
{
    use BelongsToTenant;

    public const STATUS_PENDING = 'pending';

    public const STATUS_DELIVERED = 'delivered';

    public const STATUS_FAILED = 'failed';

    protected $guarded = [];

    protected $casts = [
        'payload' => 'array',
        'response_status' => 'integer',
        'attempt' => 'integer',
        'delivered_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<WebhookSubscription, $this>
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(WebhookSubscription::class, 'webhook_subscription_id');
    }
}
