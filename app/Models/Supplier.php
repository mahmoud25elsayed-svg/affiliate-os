<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $tenant_id
 * @property string $name
 * @property string|null $company_name
 * @property string $phone
 * @property string|null $email
 * @property string|null $tax_id
 * @property string|null $address
 * @property string $status
 * @property int $default_lead_time_days
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, SupplierBatch> $batches
 * @property-read Collection<int, WholesalePrice> $wholesalePrices
 */
class Supplier extends Model
{
    use BelongsToTenant;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_SUSPENDED = 'suspended';

    protected $fillable = [
        'tenant_id',
        'name',
        'company_name',
        'phone',
        'email',
        'tax_id',
        'address',
        'status',
        'default_lead_time_days',
        'notes',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'default_lead_time_days' => 'integer',
    ];

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /**
     * @return HasMany<SupplierBatch, $this>
     */
    public function batches(): HasMany
    {
        return $this->hasMany(SupplierBatch::class);
    }

    /**
     * @return HasMany<WholesalePrice, $this>
     */
    public function wholesalePrices(): HasMany
    {
        return $this->hasMany(WholesalePrice::class);
    }
}
