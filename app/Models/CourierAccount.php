<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $tenant_id
 * @property string $name
 * @property string $driver
 * @property string|null $api_key
 * @property string|null $api_secret
 * @property string|null $account_number
 * @property string|null $webhook_secret
 * @property bool $is_active
 * @property bool $is_default
 * @property array<string, mixed>|null $settings
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class CourierAccount extends Model
{
    use BelongsToTenant;

    protected $table = 'courier_accounts';

    protected $fillable = [
        'tenant_id',
        'name',
        'driver',
        'api_key',
        'api_secret',
        'account_number',
        'webhook_secret',
        'is_active',
        'is_default',
        'settings',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_default' => 'boolean',
            'settings' => 'array',
        ];
    }

    /**
     * @return HasMany<CourierRule, $this>
     */
    public function rules(): HasMany
    {
        return $this->hasMany(CourierRule::class, 'courier_account_id');
    }

    /**
     * @return HasMany<Shipment, $this>
     */
    public function shipments(): HasMany
    {
        return $this->hasMany(Shipment::class, 'courier_account_id');
    }
}
