<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Fulfillment\Enums\ShipmentStatus;
use App\Support\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Lunar\Models\Order;

/**
 * @property int $id
 * @property string $tenant_id
 * @property int $order_id
 * @property int $courier_account_id
 * @property string $tracking_number
 * @property string|null $waybill_url
 * @property ShipmentStatus $status
 * @property int $cod_amount_minor
 * @property int $shipping_fees_minor
 * @property string|null $recipient_name
 * @property string|null $recipient_phone
 * @property string|null $delivery_city
 * @property string|null $delivery_address
 * @property array<string, mixed>|null $label_data
 * @property list<array{status: string, timestamp: string, note?: string|null}>|null $status_history
 * @property Carbon|null $dispatched_at
 * @property Carbon|null $delivered_at
 * @property Carbon|null $returned_at
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Order|null $order
 * @property-read CourierAccount|null $courierAccount
 */
class Shipment extends Model
{
    use BelongsToTenant;

    protected $table = 'shipments';

    protected $fillable = [
        'tenant_id',
        'order_id',
        'courier_account_id',
        'tracking_number',
        'waybill_url',
        'status',
        'cod_amount_minor',
        'shipping_fees_minor',
        'recipient_name',
        'recipient_phone',
        'delivery_city',
        'delivery_address',
        'label_data',
        'status_history',
        'dispatched_at',
        'delivered_at',
        'returned_at',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ShipmentStatus::class,
            'cod_amount_minor' => 'integer',
            'shipping_fees_minor' => 'integer',
            'label_data' => 'array',
            'status_history' => 'array',
            'dispatched_at' => 'datetime',
            'delivered_at' => 'datetime',
            'returned_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    /**
     * @return BelongsTo<CourierAccount, $this>
     */
    public function courierAccount(): BelongsTo
    {
        return $this->belongsTo(CourierAccount::class, 'courier_account_id');
    }

    /**
     * Append a new transition event to status_history.
     */
    public function recordStatusChange(ShipmentStatus $newStatus, ?string $note = null): void
    {
        $history = $this->status_history ?? [];
        $history[] = [
            'status' => $newStatus->value,
            'timestamp' => Carbon::now()->toIso8601String(),
            'note' => $note,
        ];

        $this->status = $newStatus;
        $this->status_history = $history;

        if ($newStatus === ShipmentStatus::DELIVERED) {
            $this->delivered_at = Carbon::now();
        } elseif ($newStatus === ShipmentStatus::RETURNED) {
            $this->returned_at = Carbon::now();
        }

        $this->save();
    }
}
