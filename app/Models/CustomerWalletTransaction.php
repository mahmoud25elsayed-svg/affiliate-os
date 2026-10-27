<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * @property int $id
 * @property string $tenant_id
 * @property int $wallet_id
 * @property int $amount_minor
 * @property string $type
 * @property string|null $reference_id
 * @property string|null $notes
 * @property Carbon $created_at
 * @property-read CustomerWallet|null $wallet
 */
class CustomerWalletTransaction extends Model
{
    use BelongsToTenant;

    public const UPDATED_AT = null;

    public const TYPE_DEPOSIT = 'deposit';

    public const TYPE_REFUND = 'refund';

    public const TYPE_PURCHASE = 'purchase';

    public const TYPE_WITHDRAWAL = 'withdrawal';

    public const TYPE_ADJUSTMENT = 'adjustment';

    protected $table = 'customer_wallet_transactions';

    protected $fillable = [
        'tenant_id',
        'wallet_id',
        'amount_minor',
        'type',
        'reference_id',
        'notes',
        'created_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'wallet_id' => 'integer',
            'amount_minor' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new LogicException('Customer wallet transactions are append-only audit records and cannot be updated.');
        });

        static::deleting(function (): void {
            throw new LogicException('Customer wallet transactions are append-only audit records and cannot be deleted.');
        });
    }

    /**
     * @return BelongsTo<CustomerWallet, $this>
     */
    public function wallet(): BelongsTo
    {
        return $this->belongsTo(CustomerWallet::class, 'wallet_id');
    }
}
