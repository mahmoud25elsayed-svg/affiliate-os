<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Location\Governorate;
use App\Support\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $tenant_id
 * @property int $governorate_id
 * @property int $rate_minor
 * @property string $delivery_time_ar
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Governorate $governorate
 */
class TenantShippingRate extends Model
{
    use BelongsToTenant;

    protected $table = 'tenant_shipping_rates';

    protected $fillable = [
        'tenant_id',
        'governorate_id',
        'rate_minor',
        'delivery_time_ar',
        'is_active',
    ];

    protected $casts = [
        'rate_minor' => 'integer',
        'is_active' => 'boolean',
    ];

    /**
     * @return BelongsTo<Governorate, $this>
     */
    public function governorate(): BelongsTo
    {
        return $this->belongsTo(Governorate::class, 'governorate_id');
    }
}
