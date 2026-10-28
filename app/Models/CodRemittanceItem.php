<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Finance\Enums\RemittanceItemMatchStatus;
use App\Support\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Lunar\Models\Order;

/**
 * @property int $id
 * @property string $tenant_id
 * @property int $cod_remittance_id
 * @property int|null $shipment_id
 * @property int|null $order_id
 * @property string $tracking_number
 * @property string $reported_status
 * @property int $reported_cod_minor
 * @property int $expected_cod_minor
 * @property int $cod_discrepancy_minor
 * @property int $shipping_fee_minor
 * @property int $expected_shipping_fee_minor
 * @property int $fee_discrepancy_minor
 * @property RemittanceItemMatchStatus $match_status
 * @property string|null $notes
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read CodRemittance|null $remittance
 * @property-read Shipment|null $shipment
 * @property-read Order|null $order
 */
class CodRemittanceItem extends Model
{
    use BelongsToTenant;

    protected $table = 'cod_remittance_items';

    protected $fillable = [
        'tenant_id',
        'cod_remittance_id',
        'shipment_id',
        'order_id',
        'tracking_number',
        'reported_status',
        'reported_cod_minor',
        'expected_cod_minor',
        'cod_discrepancy_minor',
        'shipping_fee_minor',
        'expected_shipping_fee_minor',
        'fee_discrepancy_minor',
        'match_status',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cod_remittance_id' => 'integer',
            'shipment_id' => 'integer',
            'order_id' => 'integer',
            'reported_cod_minor' => 'integer',
            'expected_cod_minor' => 'integer',
            'cod_discrepancy_minor' => 'integer',
            'shipping_fee_minor' => 'integer',
            'expected_shipping_fee_minor' => 'integer',
            'fee_discrepancy_minor' => 'integer',
            'match_status' => RemittanceItemMatchStatus::class,
        ];
    }

    /**
     * @return BelongsTo<CodRemittance, $this>
     */
    public function remittance(): BelongsTo
    {
        return $this->belongsTo(CodRemittance::class, 'cod_remittance_id');
    }

    /**
     * @return BelongsTo<Shipment, $this>
     */
    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class, 'shipment_id');
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }
}
