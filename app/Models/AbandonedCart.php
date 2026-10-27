<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $tenant_id
 * @property int|null $customer_id
 * @property string|null $session_id
 * @property string $recovery_token
 * @property string|null $customer_name
 * @property string|null $phone_e164
 * @property string|null $email
 * @property array<string, mixed> $cart_data
 * @property int $subtotal_minor
 * @property int $items_count
 * @property Carbon $last_active_at
 * @property Carbon|null $reminded_at
 * @property Carbon|null $recovered_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Customer|null $customer
 */
class AbandonedCart extends Model
{
    use BelongsToTenant;

    protected $table = 'abandoned_carts';

    protected $fillable = [
        'tenant_id',
        'customer_id',
        'session_id',
        'recovery_token',
        'customer_name',
        'phone_e164',
        'email',
        'cart_data',
        'subtotal_minor',
        'items_count',
        'last_active_at',
        'reminded_at',
        'recovered_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'customer_id' => 'integer',
            'cart_data' => 'array',
            'subtotal_minor' => 'integer',
            'items_count' => 'integer',
            'last_active_at' => 'datetime',
            'reminded_at' => 'datetime',
            'recovered_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $cart): void {
            if (empty($cart->recovery_token)) {
                $cart->recovery_token = Str::random(48);
            }
        });
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    /**
     * Scope for unrecovered abandoned carts that were abandoned more than minutes ago.
     *
     * @param  Builder<AbandonedCart>  $query
     */
    public function scopeAbandoned(Builder $query, int $minutesAgo = 30): void
    {
        $cutoff = now()->subMinutes($minutesAgo);
        $query->whereNull('recovered_at')
            ->where('last_active_at', '<=', $cutoff);
    }

    /**
     * Check if this abandoned cart has been recovered.
     */
    public function isRecovered(): bool
    {
        return $this->recovered_at !== null;
    }

    /**
     * Mark cart as recovered.
     */
    public function markRecovered(): void
    {
        $this->update(['recovered_at' => now()]);
    }

    /**
     * Mark cart as reminded.
     */
    public function markReminded(): void
    {
        $this->update(['reminded_at' => now()]);
    }
}
