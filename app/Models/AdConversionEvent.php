<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Lunar\Models\Order;

/**
 * @property int $id
 * @property string $tenant_id
 * @property int|null $order_id
 * @property int|null $affiliate_id
 * @property string $platform
 * @property string|null $pixel_id
 * @property string $event_name
 * @property string $event_id
 * @property array<string, mixed> $payload
 * @property string $status
 * @property int|null $response_code
 * @property string|null $response_body
 * @property string|null $error_message
 * @property Carbon|null $sent_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Order|null $order
 * @property-read Affiliate|null $affiliate
 */
class AdConversionEvent extends Model
{
    use BelongsToTenant;

    public const PLATFORM_META = 'meta';

    public const PLATFORM_TIKTOK = 'tiktok';

    public const STATUS_PENDING = 'pending';

    public const STATUS_SENT = 'sent';

    public const STATUS_FAILED = 'failed';

    public const EVENT_VIEW_CONTENT = 'ViewContent';

    public const EVENT_ADD_TO_CART = 'AddToCart';

    public const EVENT_INITIATE_CHECKOUT = 'InitiateCheckout';

    public const EVENT_PURCHASE = 'Purchase';

    public const EVENT_DELIVERED = 'Delivered';

    protected $guarded = [];

    protected $casts = [
        'payload' => 'array',
        'response_code' => 'integer',
        'sent_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    /**
     * @return BelongsTo<Affiliate, $this>
     */
    public function affiliate(): BelongsTo
    {
        return $this->belongsTo(Affiliate::class, 'affiliate_id');
    }
}
