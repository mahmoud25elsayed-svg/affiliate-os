<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $tenant_id
 * @property string $code
 * @property string $name
 * @property string $type
 * @property int $value_minor
 * @property int|null $min_order_value_minor
 * @property int|null $max_discount_minor
 * @property int|null $max_uses
 * @property int $uses_count
 * @property int $max_uses_per_customer
 * @property Carbon|null $starts_at
 * @property Carbon|null $expires_at
 * @property array<int, int>|null $restricted_categories
 * @property array<int, int>|null $restricted_products
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Coupon extends Model
{
    use BelongsToTenant;

    public const TYPE_PERCENTAGE = 'percentage';

    public const TYPE_FIXED = 'fixed';

    public const TYPE_FREE_SHIPPING = 'free_shipping';

    protected $fillable = [
        'tenant_id',
        'code',
        'name',
        'type',
        'value_minor',
        'min_order_value_minor',
        'max_discount_minor',
        'max_uses',
        'uses_count',
        'max_uses_per_customer',
        'starts_at',
        'expires_at',
        'restricted_categories',
        'restricted_products',
        'is_active',
    ];

    protected $casts = [
        'value_minor' => 'integer',
        'min_order_value_minor' => 'integer',
        'max_discount_minor' => 'integer',
        'max_uses' => 'integer',
        'uses_count' => 'integer',
        'max_uses_per_customer' => 'integer',
        'starts_at' => 'datetime',
        'expires_at' => 'datetime',
        'restricted_categories' => 'array',
        'restricted_products' => 'array',
        'is_active' => 'boolean',
    ];

    /**
     * @return HasMany<CouponRedemption, $this>
     */
    public function redemptions(): HasMany
    {
        return $this->hasMany(CouponRedemption::class, 'coupon_id');
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function hasStarted(): bool
    {
        return $this->starts_at === null || $this->starts_at->isPast();
    }

    public function isUsageLimitReached(): bool
    {
        return $this->max_uses !== null && $this->uses_count >= $this->max_uses;
    }

    public function isCustomerUsageLimitReached(string $customerPhoneHash): bool
    {
        if ($this->max_uses_per_customer <= 0) {
            return false;
        }

        $redemptionCount = $this->redemptions()
            ->where('customer_phone_hash', $customerPhoneHash)
            ->count();

        return $redemptionCount >= $this->max_uses_per_customer;
    }
}
