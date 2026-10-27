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
 * @property string $channel
 * @property string $provider
 * @property bool $is_active
 * @property bool $is_default
 * @property array<string, mixed>|null $credentials
 * @property string|null $sender_id
 * @property int $daily_cost_limit_minor
 * @property int $current_day_cost_minor
 * @property Carbon|null $last_cost_reset_at
 * @property array<string, mixed>|null $settings
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Tenant|null $tenant
 */
class NotificationChannel extends Model
{
    use BelongsToTenant;

    protected $table = 'notification_channels';

    protected $fillable = [
        'tenant_id',
        'channel',
        'provider',
        'is_active',
        'is_default',
        'credentials',
        'sender_id',
        'daily_cost_limit_minor',
        'current_day_cost_minor',
        'last_cost_reset_at',
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
            'credentials' => 'encrypted:array',
            'settings' => 'array',
            'daily_cost_limit_minor' => 'integer',
            'current_day_cost_minor' => 'integer',
            'last_cost_reset_at' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public function resetDailyCostIfNeeded(): void
    {
        $today = Carbon::today();
        if ($this->last_cost_reset_at === null || ! $this->last_cost_reset_at->isSameDay($today)) {
            $this->updateQuietly([
                'current_day_cost_minor' => 0,
                'last_cost_reset_at' => $today,
            ]);
            $this->current_day_cost_minor = 0;
            $this->last_cost_reset_at = $today;
        }
    }

    public function canSpend(int $amountMinor): bool
    {
        $this->resetDailyCostIfNeeded();

        return ($this->current_day_cost_minor + $amountMinor) <= $this->daily_cost_limit_minor;
    }

    public function recordSpend(int $amountMinor): void
    {
        $this->resetDailyCostIfNeeded();
        $this->increment('current_day_cost_minor', $amountMinor);
    }
}
