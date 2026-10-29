<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $tenant_id
 * @property string $name
 * @property string|null $owner_type
 * @property int|null $owner_id
 * @property string $key_hash
 * @property string $key_prefix
 * @property array<string> $scopes
 * @property int $rate_limit_per_minute
 * @property Carbon|null $last_used_at
 * @property Carbon|null $expires_at
 * @property bool $is_revoked
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Model|null $owner
 */
class ApiKey extends Model
{
    use BelongsToTenant;

    public const SCOPE_MERCHANT_PRODUCTS_READ = 'merchant:products:read';

    public const SCOPE_MERCHANT_PRODUCTS_WRITE = 'merchant:products:write';

    public const SCOPE_MERCHANT_ORDERS_READ = 'merchant:orders:read';

    public const SCOPE_MERCHANT_ORDERS_WRITE = 'merchant:orders:write';

    public const SCOPE_MERCHANT_INVENTORY_READ = 'merchant:inventory:read';

    public const SCOPE_MERCHANT_INVENTORY_WRITE = 'merchant:inventory:write';

    public const SCOPE_MERCHANT_CUSTOMERS_READ = 'merchant:customers:read';

    public const SCOPE_MERCHANT_CUSTOMERS_WRITE = 'merchant:customers:write';

    public const SCOPE_AFFILIATE_CATALOG_READ = 'affiliate:catalog:read';

    public const SCOPE_AFFILIATE_ORDERS_CREATE = 'affiliate:orders:create';

    public const SCOPE_AFFILIATE_ORDERS_READ = 'affiliate:orders:read';

    public const SCOPE_AFFILIATE_WALLET_READ = 'affiliate:wallet:read';

    public const SCOPE_MOBILE_DEVICES_MANAGE = 'mobile:devices:manage';

    protected $guarded = [];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'key_hash',
    ];

    protected $casts = [
        'scopes' => 'array',
        'rate_limit_per_minute' => 'integer',
        'is_revoked' => 'boolean',
        'last_used_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    /**
     * @return MorphTo<Model, $this>
     */
    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    public function isValid(): bool
    {
        if ($this->is_revoked) {
            return false;
        }

        if ($this->expires_at && $this->expires_at->isPast()) {
            return false;
        }

        return true;
    }

    public function hasScope(string $scope): bool
    {
        if (in_array('*', $this->scopes, true)) {
            return true;
        }

        return in_array($scope, $this->scopes, true);
    }

    public function touchUsage(): void
    {
        $this->update(['last_used_at' => now()]);
    }

    /**
     * Generate and persist an API key, returning the plain key only once.
     *
     * @param  array<string>  $scopes
     * @return array{apiKey: ApiKey, plainTextKey: string}
     */
    public static function createKey(
        Tenant $tenant,
        string $name,
        array $scopes,
        ?Model $owner = null,
        ?int $rateLimit = null,
        ?\DateTimeInterface $expiresAt = null
    ): array {
        $prefix = 'aff_live_'.Str::lower(Str::random(8));
        $secret = Str::random(32);
        $plainTextKey = "{$prefix}_{$secret}";

        $rateLimit = $rateLimit ?? (int) ($tenant->subscription?->plan?->slug === 'enterprise' ? 300 : 60);

        /** @var ApiKey $apiKey */
        $apiKey = static::create([
            'tenant_id' => (string) $tenant->id,
            'name' => $name,
            'owner_type' => $owner ? get_class($owner) : null,
            'owner_id' => $owner?->getKey(),
            'key_hash' => hash('sha256', $plainTextKey),
            'key_prefix' => $prefix,
            'scopes' => $scopes,
            'rate_limit_per_minute' => $rateLimit,
            'expires_at' => $expiresAt,
            'is_revoked' => false,
        ]);

        return [
            'apiKey' => $apiKey,
            'plainTextKey' => $plainTextKey,
        ];
    }
}
