<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Finance\Enums\CodRemittanceStatus;
use App\Support\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $tenant_id
 * @property int $courier_account_id
 * @property string $reference
 * @property CodRemittanceStatus $status
 * @property int $total_collected_minor
 * @property int $total_shipping_fees_minor
 * @property int $total_returned_fees_minor
 * @property int $net_payout_minor
 * @property int $matched_orders_count
 * @property int $discrepancy_orders_count
 * @property int $discrepancy_amount_minor
 * @property string|null $bank_reference
 * @property string|null $notes
 * @property Carbon|null $settled_at
 * @property int|null $created_by
 * @property int|null $settled_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read CourierAccount|null $courierAccount
 * @property-read Collection<int, CodRemittanceItem> $items
 * @property-read User|null $creator
 * @property-read User|null $settler
 */
class CodRemittance extends Model
{
    use BelongsToTenant;

    protected $table = 'cod_remittances';

    protected $fillable = [
        'tenant_id',
        'courier_account_id',
        'reference',
        'status',
        'total_collected_minor',
        'total_shipping_fees_minor',
        'total_returned_fees_minor',
        'net_payout_minor',
        'matched_orders_count',
        'discrepancy_orders_count',
        'discrepancy_amount_minor',
        'bank_reference',
        'notes',
        'settled_at',
        'created_by',
        'settled_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'courier_account_id' => 'integer',
            'status' => CodRemittanceStatus::class,
            'total_collected_minor' => 'integer',
            'total_shipping_fees_minor' => 'integer',
            'total_returned_fees_minor' => 'integer',
            'net_payout_minor' => 'integer',
            'matched_orders_count' => 'integer',
            'discrepancy_orders_count' => 'integer',
            'discrepancy_amount_minor' => 'integer',
            'settled_at' => 'datetime',
            'created_by' => 'integer',
            'settled_by' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<CourierAccount, $this>
     */
    public function courierAccount(): BelongsTo
    {
        return $this->belongsTo(CourierAccount::class, 'courier_account_id');
    }

    /**
     * @return HasMany<CodRemittanceItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(CodRemittanceItem::class, 'cod_remittance_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function settler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'settled_by');
    }
}

