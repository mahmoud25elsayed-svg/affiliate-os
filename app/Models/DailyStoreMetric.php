<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Money;
use App\Support\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $tenant_id
 * @property Carbon $metric_date
 * @property int $orders_count
 * @property int $confirmed_orders_count
 * @property int $delivered_orders_count
 * @property int $returned_orders_count
 * @property int $cancelled_orders_count
 * @property int $gross_revenue_minor
 * @property int $affiliate_commission_minor
 * @property int $shipping_expense_minor
 * @property int $cogs_minor
 * @property int $tax_minor
 * @property int $net_profit_minor
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class DailyStoreMetric extends Model
{
    use BelongsToTenant;

    protected $table = 'daily_store_metrics';

    protected $fillable = [
        'tenant_id',
        'metric_date',
        'orders_count',
        'confirmed_orders_count',
        'delivered_orders_count',
        'returned_orders_count',
        'cancelled_orders_count',
        'gross_revenue_minor',
        'affiliate_commission_minor',
        'shipping_expense_minor',
        'cogs_minor',
        'tax_minor',
        'net_profit_minor',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'metric_date' => 'date',
        'orders_count' => 'integer',
        'confirmed_orders_count' => 'integer',
        'delivered_orders_count' => 'integer',
        'returned_orders_count' => 'integer',
        'cancelled_orders_count' => 'integer',
        'gross_revenue_minor' => 'integer',
        'affiliate_commission_minor' => 'integer',
        'shipping_expense_minor' => 'integer',
        'cogs_minor' => 'integer',
        'tax_minor' => 'integer',
        'net_profit_minor' => 'integer',
    ];

    public function grossRevenueMoney(): Money
    {
        return Money::fromMinor($this->gross_revenue_minor);
    }

    public function netProfitMoney(): Money
    {
        return Money::fromMinor($this->net_profit_minor);
    }

    public function affiliateCommissionMoney(): Money
    {
        return Money::fromMinor($this->affiliate_commission_minor);
    }

    public function shippingExpenseMoney(): Money
    {
        return Money::fromMinor($this->shipping_expense_minor);
    }

    public function cogsMoney(): Money
    {
        return Money::fromMinor($this->cogs_minor);
    }

    public function taxMoney(): Money
    {
        return Money::fromMinor($this->tax_minor);
    }

    public function deliveryRate(): float
    {
        if ($this->orders_count <= 0) {
            return 0.0;
        }

        return round(($this->delivered_orders_count / $this->orders_count) * 100, 1);
    }

    public function returnRate(): float
    {
        if ($this->orders_count <= 0) {
            return 0.0;
        }

        return round(($this->returned_orders_count / $this->orders_count) * 100, 1);
    }
}
