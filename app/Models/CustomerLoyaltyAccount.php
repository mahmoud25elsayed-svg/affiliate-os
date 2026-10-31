<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

/**
 * @property int $id
 * @property string $tenant_id
 * @property int $customer_id
 * @property int $points_balance
 * @property int $total_earned_points
 * @property int $total_redeemed_points
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Customer|null $customer
 * @property-read Collection<int, CustomerLoyaltyTransaction> $transactions
 */
class CustomerLoyaltyAccount extends Model
{
    use BelongsToTenant;

    protected $table = 'customer_loyalty_accounts';

    protected $fillable = [
        'tenant_id',
        'customer_id',
        'points_balance',
        'total_earned_points',
        'total_redeemed_points',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'customer_id' => 'integer',
            'points_balance' => 'integer',
            'total_earned_points' => 'integer',
            'total_redeemed_points' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    /**
     * @return HasMany<CustomerLoyaltyTransaction, $this>
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(CustomerLoyaltyTransaction::class, 'loyalty_account_id');
    }

    /**
     * Atomically award loyalty points.
     */
    public function award(int $points, string $type, ?int $orderId = null, ?string $notes = null): CustomerLoyaltyTransaction
    {
        if ($points <= 0) {
            throw new InvalidArgumentException('Points to award must be strictly positive.');
        }

        return DB::transaction(function () use ($points, $type, $orderId, $notes): CustomerLoyaltyTransaction {
            /** @var self $account */
            $account = self::query()->where('id', $this->id)->lockForUpdate()->firstOrFail();

            $account->points_balance += $points;
            $account->total_earned_points += $points;
            $account->save();

            return CustomerLoyaltyTransaction::create([
                'tenant_id' => $account->tenant_id,
                'loyalty_account_id' => $account->id,
                'points' => $points,
                'balance_after' => $account->points_balance,
                'type' => $type,
                'order_id' => $orderId,
                'notes' => $notes,
                'created_at' => now(),
            ]);
        });
    }

    /**
     * Atomically redeem loyalty points.
     */
    public function redeem(int $points, string $type, ?int $orderId = null, ?string $notes = null): CustomerLoyaltyTransaction
    {
        if ($points <= 0) {
            throw new InvalidArgumentException('Points to redeem must be strictly positive.');
        }

        return DB::transaction(function () use ($points, $type, $orderId, $notes): CustomerLoyaltyTransaction {
            /** @var self $account */
            $account = self::query()->where('id', $this->id)->lockForUpdate()->firstOrFail();

            if ($account->points_balance < $points) {
                throw new RuntimeException(sprintf(
                    __('رصيد نقاط الولاء غير كافٍ. الرصيد الحالي: %d نقطة، المطلوب استبداله: %d نقطة.'),
                    $account->points_balance,
                    $points
                ));
            }

            $account->points_balance -= $points;
            $account->total_redeemed_points += $points;
            $account->save();

            return CustomerLoyaltyTransaction::create([
                'tenant_id' => $account->tenant_id,
                'loyalty_account_id' => $account->id,
                'points' => -$points,
                'balance_after' => $account->points_balance,
                'type' => $type,
                'order_id' => $orderId,
                'notes' => $notes,
                'created_at' => now(),
            ]);
        });
    }
}
