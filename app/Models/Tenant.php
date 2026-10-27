<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Stancl\Tenancy\Database\Concerns\HasDomains;
use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;

class Tenant extends BaseTenant
{
    use HasDomains;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_SUSPENDED = 'suspended';

    public const STATUS_PROVISIONING = 'provisioning';

    public const STATUS_ARCHIVED = 'archived';

    /**
     * @return list<string>
     */
    public static function getCustomColumns(): array
    {
        return [
            'id',
            'name',
            'status',
        ];
    }

    /**
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'tenant_id');
    }

    /**
     * @return HasOne<User, $this>
     */
    public function owner(): HasOne
    {
        return $this->hasOne(User::class, 'tenant_id')->oldestOfMany();
    }

    /**
     * @return HasMany<Subscription, $this>
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class, 'tenant_id');
    }

    /**
     * @return HasOne<Subscription, $this>
     */
    public function subscription(): HasOne
    {
        return $this->hasOne(Subscription::class, 'tenant_id')->latestOfMany();
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isSuspended(): bool
    {
        return $this->status === 'suspended';
    }

    public function isProvisioning(): bool
    {
        return $this->status === 'provisioning';
    }

    public function isArchived(): bool
    {
        return $this->status === 'archived';
    }
}
