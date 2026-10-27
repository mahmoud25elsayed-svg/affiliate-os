<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $phone_hash
 * @property string|null $tenant_id
 * @property int $delivered_orders_count
 * @property int $refused_orders_count
 * @property int $total_orders_count
 * @property Carbon|null $last_seen_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class CustomerReputation extends Model
{
    protected $table = 'customer_reputations';

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'delivered_orders_count' => 'integer',
            'refused_orders_count' => 'integer',
            'total_orders_count' => 'integer',
            'last_seen_at' => 'datetime',
        ];
    }

    public function getDeliveryRate(): float
    {
        $concluded = $this->delivered_orders_count + $this->refused_orders_count;
        if ($concluded === 0) {
            return 100.0;
        }

        return round(($this->delivered_orders_count / $concluded) * 100, 2);
    }

    public function getRiskLevel(): string
    {
        $concluded = $this->delivered_orders_count + $this->refused_orders_count;
        if ($concluded < 2) {
            return 'neutral'; // New customer
        }

        $rate = $this->getDeliveryRate();
        if ($rate >= 80.0) {
            return 'low';
        }
        if ($rate >= 50.0) {
            return 'medium';
        }

        return 'high';
    }
}
