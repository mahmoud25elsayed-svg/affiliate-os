<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $tenant_id
 * @property string $name
 * @property string $code
 * @property string $type
 * @property string|null $address
 * @property int|null $governorate_id
 * @property bool $is_default
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Warehouse extends Model
{
    use BelongsToTenant;

    protected $table = 'warehouses';

    protected $fillable = [
        'tenant_id',
        'name',
        'code',
        'type',
        'address',
        'governorate_id',
        'is_default',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @param  Builder<Warehouse>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * @param  Builder<Warehouse>  $query
     */
    public function scopeDefault(Builder $query): void
    {
        $query->where('is_default', true);
    }

    /**
     * The current tenant's default active warehouse, creating the main warehouse on first use so
     * stock received from the panel always has somewhere to land.
     */
    public static function resolveDefault(): self
    {
        /** @var self|null $warehouse */
        $warehouse = self::query()->active()->orderByDesc('is_default')->orderBy('id')->first();

        if ($warehouse === null) {
            /** @var self $warehouse */
            $warehouse = self::query()->create([
                'name' => __('المستودع الرئيسي'),
                'code' => 'MAIN',
                'type' => 'own',
                'is_default' => true,
                'is_active' => true,
            ]);
        }

        return $warehouse;
    }

    /**
     * @return HasMany<InventoryBalance, $this>
     */
    public function balances(): HasMany
    {
        return $this->hasMany(InventoryBalance::class, 'warehouse_id');
    }

    /**
     * @return HasMany<InventoryMovement, $this>
     */
    public function movements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class, 'warehouse_id');
    }
}
