<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Tenancy\Concerns\BelongsToTenant;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Lunar\Models\Order;

/**
 * @property int $id
 * @property string|null $tenant_id
 * @property string $ticket_number
 * @property string $level
 * @property string $subject
 * @property string $priority
 * @property string $status
 * @property string $department
 * @property int|null $created_by_user_id
 * @property int|null $assigned_to_user_id
 * @property int|null $customer_id
 * @property int|null $affiliate_id
 * @property int|null $order_id
 * @property int|null $payout_request_id
 * @property CarbonInterface|null $sla_due_at
 * @property CarbonInterface|null $first_response_at
 * @property CarbonInterface|null $resolved_at
 * @property CarbonInterface|null $closed_at
 * @property CarbonInterface $created_at
 * @property CarbonInterface $updated_at
 * @property-read Tenant|null $tenant
 * @property-read User|null $assignedTo
 * @property-read User|null $createdBy
 * @property-read Customer|null $customer
 * @property-read Affiliate|null $affiliate
 * @property-read Order|null $order
 * @property-read PayoutRequest|null $payoutRequest
 * @property-read Collection<int, TicketMessage> $messages
 * @property-read TicketMessage|null $latestMessage
 */
class Ticket extends Model
{
    use BelongsToTenant;

    public const LEVEL_STORE = 'store';

    public const LEVEL_PLATFORM = 'platform';

    public const PRIORITY_LOW = 'low';

    public const PRIORITY_MEDIUM = 'medium';

    public const PRIORITY_HIGH = 'high';

    public const PRIORITY_URGENT = 'urgent';

    public const STATUS_OPEN = 'open';

    public const STATUS_PENDING_CUSTOMER = 'pending_customer';

    public const STATUS_PENDING_AGENT = 'pending_agent';

    public const STATUS_RESOLVED = 'resolved';

    public const STATUS_CLOSED = 'closed';

    public const DEPT_GENERAL = 'general';

    public const DEPT_ORDERS = 'orders';

    public const DEPT_FINANCE = 'finance';

    public const DEPT_SHIPPING = 'shipping';

    public const DEPT_TECHNICAL = 'technical';

    protected $table = 'tickets';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'ticket_number',
        'level',
        'subject',
        'priority',
        'status',
        'department',
        'created_by_user_id',
        'assigned_to_user_id',
        'customer_id',
        'affiliate_id',
        'order_id',
        'payout_request_id',
        'sla_due_at',
        'first_response_at',
        'resolved_at',
        'closed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sla_due_at' => 'datetime',
            'first_response_at' => 'datetime',
            'resolved_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
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
    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return BelongsTo<Affiliate, $this>
     */
    public function affiliate(): BelongsTo
    {
        return $this->belongsTo(Affiliate::class);
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return BelongsTo<PayoutRequest, $this>
     */
    public function payoutRequest(): BelongsTo
    {
        return $this->belongsTo(PayoutRequest::class);
    }

    /**
     * @return HasMany<TicketMessage, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(TicketMessage::class)->oldest();
    }

    /**
     * @return HasOne<TicketMessage, $this>
     */
    public function latestMessage(): HasOne
    {
        return $this->hasOne(TicketMessage::class)->latestOfMany();
    }

    /**
     * Check if the SLA has been breached.
     */
    public function isSlaBreached(): bool
    {
        if ($this->isClosedOrResolved()) {
            return false;
        }

        if (! $this->sla_due_at) {
            return false;
        }

        return now()->isAfter($this->sla_due_at);
    }

    public function isClosedOrResolved(): bool
    {
        return in_array($this->status, [self::STATUS_RESOLVED, self::STATUS_CLOSED], true);
    }

    /**
     * Return First Response Time in minutes.
     */
    public function getFirstResponseTimeMinutes(): ?int
    {
        if (! $this->first_response_at) {
            return null;
        }

        return (int) $this->created_at->diffInMinutes($this->first_response_at);
    }

    /**
     * Return Resolution Time in minutes.
     */
    public function getResolutionTimeMinutes(): ?int
    {
        if (! $this->resolved_at) {
            return null;
        }

        return (int) $this->created_at->diffInMinutes($this->resolved_at);
    }

    /**
     * Calculate SLA Due date based on priority.
     * Urgent: 2h, High: 8h, Medium: 24h, Low: 48h.
     */
    public static function calculateSlaDueAt(string $priority, ?Carbon $from = null): Carbon
    {
        $base = $from ? $from->copy() : now();

        return match ($priority) {
            self::PRIORITY_URGENT => $base->addHours(2),
            self::PRIORITY_HIGH => $base->addHours(8),
            self::PRIORITY_LOW => $base->addHours(48),
            default => $base->addHours(24),
        };
    }

    /**
     * Generate unique ticket number: TCK-YEAR-RANDOM
     */
    public static function generateTicketNumber(?string $prefix = 'TCK'): string
    {
        $year = now()->format('Y');
        $random = strtoupper(substr(bin2hex(random_bytes(3)), 0, 5));

        return "{$prefix}-{$year}-{$random}";
    }

    /**
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNotIn('status', [self::STATUS_RESOLVED, self::STATUS_CLOSED]);
    }

    /**
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeBreachedSla(Builder $query): Builder
    {
        return $query->whereNotNull('sla_due_at')
            ->where('sla_due_at', '<', now())
            ->whereNotIn('status', [self::STATUS_RESOLVED, self::STATUS_CLOSED]);
    }
}
