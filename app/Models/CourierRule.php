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
 * @property int $courier_account_id
 * @property int|null $governorate_id
 * @property int $priority
 * @property float|null $max_weight_kg
 * @property int|null $cod_limit_minor
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class CourierRule extends Model
{
    use BelongsToTenant;

    protected $table = 'courier_rules';

    protected $fillable = [
        'tenant_id',
        'courier_account_id',
        'governorate_id',
        'priority',
        'max_weight_kg',
        'cod_limit_minor',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'priority' => 'integer',
            'cod_limit_minor' => 'integer',
            'max_weight_kg' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<CourierAccount, $this>
     */
    public function courierAccount(): BelongsTo
    {
        return $this->belongsTo(CourierAccount::class, 'courier_account_id');
    }

    /**
     * @return BelongsTo<Governorate, $this>
     */
    public function governorate(): BelongsTo
    {
        return $this->belongsTo(Governorate::class, 'governorate_id');
    }
}

