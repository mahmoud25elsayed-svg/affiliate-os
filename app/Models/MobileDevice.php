<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $tenant_id
 * @property int|null $user_id
 * @property int|null $affiliate_id
 * @property string $device_token
 * @property string $platform
 * @property string $app_type
 * @property string|null $device_name
 * @property string|null $app_version
 * @property string $locale
 * @property bool $is_active
 * @property Carbon|null $last_active_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $user
 * @property-read Affiliate|null $affiliate
 * @property-read Tenant|null $tenant
 *
 * @method static Builder<static> active()
 * @method static Builder<static> forApp(string $appType)
 */
class MobileDevice extends Model
{
    use BelongsToTenant;

    public const PLATFORM_IOS = 'ios';

    public const PLATFORM_ANDROID = 'android';

    public const APP_TYPE_CUSTOMER = 'customer';

    public const APP_TYPE_AFFILIATE = 'affiliate';

    public const APP_TYPE_MERCHANT = 'merchant';

    /**
     * @var list<string>
     */
    public const SUPPORTED_PLATFORMS = [
        self::PLATFORM_IOS,
        self::PLATFORM_ANDROID,
    ];

    /**
     * @var list<string>
     */
    public const SUPPORTED_APP_TYPES = [
        self::APP_TYPE_CUSTOMER,
        self::APP_TYPE_AFFILIATE,
        self::APP_TYPE_MERCHANT,
    ];

    /**
     * @var list<string>
     */
    protected $guarded = [];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'is_active' => 'boolean',
        'last_active_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Affiliate, $this>
     */
    public function affiliate(): BelongsTo
    {
        return $this->belongsTo(Affiliate::class);
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeForApp(Builder $query, string $appType): Builder
    {
        return $query->where('app_type', $appType);
    }

    /**
     * Mark device as active and touch last activity timestamp.
     */
    public function touchActive(): void
    {
        $this->update([
            'is_active' => true,
            'last_active_at' => Carbon::now(),
        ]);
    }

    /**
     * Mark device as inactive (unregistered / logged out).
     */
    public function deactivate(): void
    {
        $this->update([
            'is_active' => false,
        ]);
    }
}
