<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Tenancy\Concerns\BelongsToTenant;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string|null $tenant_id
 * @property int $ticket_id
 * @property int|null $user_id
 * @property int|null $affiliate_id
 * @property int|null $customer_id
 * @property string $sender_type
 * @property string $message
 * @property bool $is_internal_note
 * @property array<string, mixed>|null $attachments
 * @property CarbonInterface $created_at
 * @property CarbonInterface $updated_at
 * @property-read Ticket $ticket
 * @property-read User|null $user
 * @property-read Affiliate|null $affiliate
 * @property-read Customer|null $customer
 */
class TicketMessage extends Model
{
    use BelongsToTenant;

    public const SENDER_STAFF = 'staff';

    public const SENDER_CUSTOMER = 'customer';

    public const SENDER_AFFILIATE = 'affiliate';

    public const SENDER_PLATFORM_ADMIN = 'platform_admin';

    public const SENDER_SYSTEM = 'system';

    protected $table = 'ticket_messages';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'ticket_id',
        'user_id',
        'affiliate_id',
        'customer_id',
        'sender_type',
        'message',
        'is_internal_note',
        'attachments',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_internal_note' => 'boolean',
            'attachments' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Ticket, $this>
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Affiliate, $this>
     */
    public function affiliate(): BelongsTo
    {
        return $this->belongsTo(Affiliate::class);
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Get sender display name.
     */
    public function getSenderName(): string
    {
        return match ($this->sender_type) {
            self::SENDER_STAFF => $this->user ? $this->user->name : 'فريق الدعم الفني',
            self::SENDER_AFFILIATE => $this->affiliate ? $this->affiliate->name : 'المسوق',
            self::SENDER_CUSTOMER => $this->customer ? $this->customer->name : 'العميل',
            self::SENDER_PLATFORM_ADMIN => 'إدارة المنصة',
            default => 'النظام التلقائي',
        };
    }

    /**
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopePublicMessages(Builder $query): Builder
    {
        return $query->where('is_internal_note', false);
    }

    /**
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeInternalNotes(Builder $query): Builder
    {
        return $query->where('is_internal_note', true);
    }
}
