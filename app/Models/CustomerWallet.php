<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Tenancy\Concerns\BelongsToTenant;
use DomainException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Lunar\Models\Customer;

/**
 * @property int $id
 * @property string $tenant_id
 * @property int $customer_id
 * @property int $balance_minor
 * @property string $currency
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Customer|null $customer
 * @property-read Collection<int, CustomerWalletTransaction> $transactions
 */
class CustomerWallet extends Model
{
    use BelongsToTenant;

    protected $table = 'customer_wallets';

    protected $fillable = [
        'tenant_id',
        'customer_id',
        'balance_minor',
        'currency',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'customer_id' => 'integer',
            'balance_minor' => 'integer',
            'is_active' => 'boolean',
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
     * @return HasMany<CustomerWalletTransaction, $this>
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(CustomerWalletTransaction::class, 'wallet_id');
    }

    /**
     * Credit the wallet balance.
     */
    public function credit(int $amountMinor, string $type, ?string $referenceId = null, ?string $notes = null): CustomerWalletTransaction
    {
        if ($amountMinor <= 0) {
            throw new InvalidArgumentException('Credit amount must be positive.');
        }

        return DB::transaction(function () use ($amountMinor, $type, $referenceId, $notes): CustomerWalletTransaction {
            /** @var self $wallet */
            $wallet = self::query()->where('id', $this->id)->lockForUpdate()->firstOrFail();

            $wallet->balance_minor += $amountMinor;
            $wallet->save();

            return CustomerWalletTransaction::create([
                'wallet_id' => $wallet->id,
                'amount_minor' => $amountMinor,
                'type' => $type,
                'reference_id' => $referenceId,
                'notes' => $notes,
            ]);
        });
    }

    /**
     * Debit the wallet balance.
     */
    public function debit(int $amountMinor, string $type, ?string $referenceId = null, ?string $notes = null): CustomerWalletTransaction
    {
        if ($amountMinor <= 0) {
            throw new InvalidArgumentException('Debit amount must be positive.');
        }

        return DB::transaction(function () use ($amountMinor, $type, $referenceId, $notes): CustomerWalletTransaction {
            /** @var self $wallet */
            $wallet = self::query()->where('id', $this->id)->lockForUpdate()->firstOrFail();

            if ($wallet->balance_minor < $amountMinor) {
                throw new DomainException(sprintf(
                    'Insufficient wallet balance. Available: %d, Requested: %d.',
                    $wallet->balance_minor,
                    $amountMinor
                ));
            }

            $wallet->balance_minor -= $amountMinor;
            $wallet->save();

            return CustomerWalletTransaction::create([
                'wallet_id' => $wallet->id,
                'amount_minor' => -$amountMinor,
                'type' => $type,
                'reference_id' => $referenceId,
                'notes' => $notes,
            ]);
        });
    }
}
