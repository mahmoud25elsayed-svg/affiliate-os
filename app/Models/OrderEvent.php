<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Orders\Enums\OrderStatus;
use App\Support\Enums\ReasonCode;
use App\Support\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;
use Lunar\Models\Order;

/**
 * @property int $id
 * @property string $tenant_id
 * @property int $order_id
 * @property string|null $from_status
 * @property string $to_status
 * @property ReasonCode|null $reason_code
 * @property string $actor_type
 * @property string|null $actor_id
 * @property string|null $actor_name
 * @property string|null $note
 * @property array<string, mixed>|null $meta
 * @property Carbon $created_at
 * @property-read Order|null $order
 */
class OrderEvent extends Model
{
    use BelongsToTenant;

    public $timestamps = false;

    protected $table = 'order_events';

    protected $fillable = [
        'tenant_id',
        'order_id',
        'from_status',
        'to_status',
        'reason_code',
        'actor_type',
        'actor_id',
        'actor_name',
        'note',
        'meta',
        'created_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'reason_code' => ReasonCode::class,
            'meta' => 'array',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new LogicException('OrderEvent is append-only and cannot be updated.');
        });

        static::deleting(function (): void {
            throw new LogicException('OrderEvent is append-only and cannot be deleted.');
        });
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function fromStatusEnum(): ?OrderStatus
    {
        return $this->from_status !== null ? OrderStatus::tryFrom($this->from_status) : null;
    }

    public function toStatusEnum(): ?OrderStatus
    {
        return OrderStatus::tryFrom($this->to_status);
    }

    public function actorLabel(): string
    {
        $name = $this->actor_name ?? $this->actor_id;

        return match ($this->actor_type) {
            'system' => __('النظام الآلي'),
            'staff' => $name !== null ? __('الموظف (:name)', ['name' => $name]) : __('موظف المتجر'),
            'customer' => $name !== null ? __('العميل (:name)', ['name' => $name]) : __('العميل'),
            'affiliate' => $name !== null ? __('المسوق (:name)', ['name' => $name]) : __('المسوق'),
            default => $this->actor_type,
        };
    }
}
