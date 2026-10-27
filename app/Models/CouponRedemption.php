<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $tenant_id
 * @property int $coupon_id
 * @property int|null $order_id
 * @property string $customer_phone_hash
 * @property int $discount_minor
 * @property Carbon $created_at
 * @property-read Coupon $coupon
 */
class CouponRedemption extends Model
{
    use BelongsToTenant;

    public $timestamps = false;

    protected $fillable = [
        'tenant_id',
        'coupon_id',
        'order_id',
        'customer_phone_hash',
        'discount_minor',
        'created_at',
    ];

    protected $casts = [
        'discount_minor' => 'integer',
        'created_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<Coupon, $this>
     */
    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class, 'coupon_id');
    }
}
