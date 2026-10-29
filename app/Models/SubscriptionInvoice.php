<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $invoice_number
 * @property int $subscription_id
 * @property string $tenant_id
 * @property int $amount
 * @property string $currency
 * @property string $status
 * @property Carbon $billing_period_starts_at
 * @property Carbon $billing_period_ends_at
 * @property Carbon $due_at
 * @property Carbon|null $paid_at
 * @property string|null $payment_method
 * @property string|null $payment_reference
 * @property string|null $receipt_path
 * @property string|null $notes
 * @property string|null $admin_notes
 * @property int|null $approved_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Subscription $subscription
 * @property-read Tenant $tenant
 * @property-read User|null $approver
 */
class SubscriptionInvoice extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_PAID = 'paid';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_VOID = 'void';

    protected $fillable = [
        'invoice_number',
        'subscription_id',
        'target_plan_id',
        'target_cycle',
        'tenant_id',
        'amount',
        'currency',
        'status',
        'billing_period_starts_at',
        'billing_period_ends_at',
        'due_at',
        'paid_at',
        'payment_method',
        'payment_reference',
        'receipt_path',
        'notes',
        'admin_notes',
        'approved_by',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'amount' => 'integer',
        'billing_period_starts_at' => 'datetime',
        'billing_period_ends_at' => 'datetime',
        'due_at' => 'datetime',
        'paid_at' => 'datetime',
        'approved_by' => 'integer',
    ];

    /**
     * @return BelongsTo<Subscription, $this>
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    /**
     * @return BelongsTo<Plan, $this>
     */
    public function targetPlan(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'target_plan_id');
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function amountMoney(): Money
    {
        return Money::fromMinor($this->amount, $this->currency);
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isPaid(): bool
    {
        return $this->status === self::STATUS_PAID;
    }

    public function isRejected(): bool
    {
        return $this->status === self::STATUS_REJECTED;
    }
}
