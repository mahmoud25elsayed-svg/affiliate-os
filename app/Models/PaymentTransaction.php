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
 * @property string $gateway
 * @property string $transaction_ref
 * @property string $status
 * @property int $amount_minor
 * @property int $refunded_amount_minor
 * @property string $currency
 * @property string|null $payment_method
 * @property array<string, mixed>|null $gateway_payload
 * @property string|null $error_message
 * @property Carbon|null $paid_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Order|null $order
 */
class PaymentTransaction extends Model
{
    use BelongsToTenant;

    public const STATUS_PENDING = 'pending';

    public const STATUS_PAID = 'paid';

    public const STATUS_FAILED = 'failed';

    public const STATUS_REFUNDED = 'refunded';

    public const STATUS_PARTIALLY_REFUNDED = 'partially_refunded';

    protected $table = 'payment_transactions';

    protected $fillable = [
        'tenant_id',
        'order_id',
        'gateway',
        'transaction_ref',
        'status',
        'amount_minor',
        'refunded_amount_minor',
        'currency',
        'payment_method',
        'gateway_payload',
        'error_message',
        'paid_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'order_id' => 'integer',
            'amount_minor' => 'integer',
            'refunded_amount_minor' => 'integer',
            'gateway_payload' => 'array',
            'paid_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function isPaid(): bool
    {
        return $this->status === self::STATUS_PAID;
    }

    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    public function isRefunded(): bool
    {
        return in_array($this->status, [self::STATUS_REFUNDED, self::STATUS_PARTIALLY_REFUNDED], true);
    }
}
