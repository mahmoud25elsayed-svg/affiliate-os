<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $tenant_id
 * @property string $gateway
 * @property bool $is_enabled
 * @property string $mode
 * @property array<string, mixed>|null $credentials
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class TenantPaymentGateway extends Model
{
    use BelongsToTenant;

    protected $table = 'tenant_payment_gateways';

    protected $fillable = [
        'tenant_id',
        'gateway',
        'is_enabled',
        'mode',
        'credentials',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'credentials' => 'encrypted:array',
        ];
    }

    /**
     * Scope for enabled gateways.
     *
     * @param  Builder<TenantPaymentGateway>  $query
     */
    public function scopeEnabled(Builder $query): void
    {
        $query->where('is_enabled', true);
    }

    /**
     * Check if gateway is ready and configured.
     */
    public function isConfigured(): bool
    {
        return $this->is_enabled && ! empty($this->credentials);
    }

    /**
     * Get a specific credential value.
     */
    public function getCredential(string $key, mixed $default = null): mixed
    {
        return $this->credentials[$key] ?? $default;
    }
}
