<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $tenant_id
 * @property int $plan_id
 * @property string $status
 * @property string $billing_cycle
 * @property Carbon|null $trial_ends_at
 * @property Carbon|null $current_period_starts_at
 * @property Carbon|null $current_period_ends_at
 * @property Carbon|null $grace_period_ends_at
 * @property Carbon|null $cancelled_at
 * @property string|null $payment_gateway
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Tenant $tenant
 * @property-read Plan $plan
 */
class Subscription extends Model
{
    public const STATUS_TRIAL = 'trial';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_PAST_DUE = 'past_due';

    public const STATUS_SUSPENDED = 'suspended';

    public const STATUS_CANCELLED = 'cancelled';

    public const CYCLE_MONTHLY = 'monthly';

    public const CYCLE_YEARLY = 'yearly';

    protected $fillable = [
        'tenant_id',
        'plan_id',
        'status',
        'billing_cycle',
        'trial_ends_at',
        'current_period_starts_at',
        'current_period_ends_at',
        'grace_period_ends_at',
        'cancelled_at',
        'payment_gateway',
        'metadata',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'trial_ends_at' => 'datetime',
        'current_period_starts_at' => 'datetime',
        'current_period_ends_at' => 'datetime',
        'grace_period_ends_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'metadata' => 'array',
    ];

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    /**
     * @return BelongsTo<Plan, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /**
     * @return HasMany<SubscriptionEvent, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(SubscriptionEvent::class)->orderByDesc('created_at');
    }

    /**
     * @return HasMany<SubscriptionInvoice, $this>
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(SubscriptionInvoice::class)->orderByDesc('created_at');
    }

    public function isTrial(): bool
    {
        return $this->status === self::STATUS_TRIAL;
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isPastDue(): bool
    {
        return $this->status === self::STATUS_PAST_DUE;
    }

    public function isSuspended(): bool
    {
        return $this->status === self::STATUS_SUSPENDED;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    /**
     * Usable states where the store can operate normally.
     */
    public function isUsable(): bool
    {
        if ($this->isSuspended() || $this->isCancelled()) {
            return false;
        }

        if ($this->isTrial()) {
            return $this->trial_ends_at !== null && $this->trial_ends_at->isFuture();
        }

        if ($this->isActive() || $this->isPastDue()) {
            return true;
        }

        return false;
    }

    public function daysLeftInTrial(): int
    {
        if (! $this->isTrial() || $this->trial_ends_at === null) {
            return 0;
        }

        return max(0, (int) now()->diffInDays($this->trial_ends_at, false));
    }

    public function daysLeftInPeriod(): int
    {
        $target = $this->isTrial() ? $this->trial_ends_at : $this->current_period_ends_at;

        if ($target === null) {
            return 0;
        }

        return max(0, (int) now()->diffInDays($target, false));
    }
}
