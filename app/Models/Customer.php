<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Phone\EgyptianPhone;
use App\Support\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Lunar\Models\Order;

/**
 * @property int $id
 * @property string $tenant_id
 * @property string $first_name
 * @property string|null $last_name
 * @property string|null $email
 * @property string|null $phone_e164
 * @property string|null $phone_hash
 * @property string|null $password
 * @property Carbon|null $phone_verified_at
 * @property Carbon|null $email_verified_at
 * @property bool $is_active
 * @property string|null $remember_token
 * @property Carbon|null $anonymized_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
class Customer extends Authenticatable
{
    use BelongsToTenant, Notifiable, SoftDeletes;

    protected $table = 'customers';

    protected $guarded = ['id'];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'phone_verified_at' => 'datetime',
            'email_verified_at' => 'datetime',
            'anonymized_at' => 'datetime',
            'is_active' => 'boolean',
            'password' => 'hashed',
        ];
    }

    public function getFullNameAttribute(): string
    {
        if ($this->last_name) {
            return "{$this->first_name} {$this->last_name}";
        }

        return $this->first_name;
    }

    public function setPhoneAttribute(?string $value): void
    {
        if ($value === null || trim($value) === '') {
            $this->attributes['phone_e164'] = null;
            $this->attributes['phone_hash'] = null;

            return;
        }

        $phone = EgyptianPhone::tryParse($value);
        if ($phone !== null) {
            $this->attributes['phone_e164'] = $phone->e164;
            $this->attributes['phone_hash'] = $phone->hash();
        } else {
            $this->attributes['phone_e164'] = $value;
            $this->attributes['phone_hash'] = hash('sha256', $value);
        }
    }

    /**
     * @return HasMany<CustomerAddress, $this>
     */
    public function addresses(): HasMany
    {
        return $this->hasMany(CustomerAddress::class, 'customer_id');
    }

    /**
     * @return HasOne<CustomerAddress, $this>
     */
    public function defaultAddress(): HasOne
    {
        return $this->hasOne(CustomerAddress::class, 'customer_id')->where('is_default', true);
    }

    /**
     * @return HasMany<Order, $this>
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'storefront_customer_id')->latest();
    }

    /**
     * @return HasMany<CustomerWishlist, $this>
     */
    public function wishlists(): HasMany
    {
        return $this->hasMany(CustomerWishlist::class, 'customer_id');
    }

    /**
     * @return HasMany<ProductReview, $this>
     */
    public function reviews(): HasMany
    {
        return $this->hasMany(ProductReview::class, 'customer_id');
    }
}
