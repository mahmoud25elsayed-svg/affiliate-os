<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;
use Lunar\Models\Order;

/**
 * @property int $id
 * @property string $tenant_id
 * @property int $loyalty_account_id
 * @property int $points
 * @property int $balance_after
 * @property string $type
 * @property int|null $order_id
 * @property string|null $notes
 * @property Carbon $created_at
 * @property-read CustomerLoyaltyAccount|null $account
 * @property-read Order|null $order
 */
class CustomerLoyaltyTransaction extends Model
{
    use BelongsToTenant;

    public const UPDATED_AT = null;

    public const TYPE_EARN_ORDER = 'earn_order';

    public const TYPE_REDEEM_ORDER = 'redeem_order';

    public const TYPE_ADJUSTMENT = 'adjustment';

    public const TYPE_SIGNUP_BONUS = 'signup_bonus';

    protected $table = 'customer_loyalty_transactions';

    protected $fillable = [
        'tenant_id',
        'loyalty_account_id',
        'points',
        'balance_after',
        'type',
        'order_id',
        'notes',
        'created_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'loyalty_account_id' => 'integer',
            'points' => 'integer',
            'balance_after' => 'integer',
            'order_id' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new LogicException('Customer loyalty transactions are append-only audit records and cannot be updated.');
        });

        static::deleting(function (): void {
            throw new LogicException('Customer loyalty transactions are append-only audit records and cannot be deleted.');
        });
    }

    /**
     * @return BelongsTo<CustomerLoyaltyAccount, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(CustomerLoyaltyAccount::class, 'loyalty_account_id');
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }
}
