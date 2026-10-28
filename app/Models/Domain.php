<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Stancl\Tenancy\Database\Models\Domain as BaseDomain;

/**
 * @property int $id
 * @property string $domain
 * @property string $tenant_id
 * @property bool $is_primary
 * @property bool $is_custom
 * @property string $status
 * @property string|null $verification_token
 * @property string $verification_method
 * @property Carbon|null $verified_at
 * @property Carbon|null $last_verified_at
 * @property string $ssl_status
 * @property string|null $ssl_provider
 * @property Carbon|null $ssl_installed_at
 * @property array<string, mixed>|null $dns_records
 * @property bool $redirect_to_primary
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Tenant $tenant
 */
class Domain extends BaseDomain
{
    public const STATUS_PENDING_VERIFICATION = 'pending_verification';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_FAILED = 'failed';

    public const STATUS_SUSPENDED = 'suspended';

    public const SSL_STATUS_PENDING = 'pending';

    public const SSL_STATUS_INSTALLING = 'installing';

    public const SSL_STATUS_ACTIVE = 'active';

    public const SSL_STATUS_FAILED = 'failed';

    protected $guarded = [];

    protected $casts = [
        'is_primary' => 'boolean',
        'is_custom' => 'boolean',
        'redirect_to_primary' => 'boolean',
        'verified_at' => 'datetime',
        'last_verified_at' => 'datetime',
        'ssl_installed_at' => 'datetime',
        'dns_records' => 'array',
    ];

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id', 'id');
    }

    public function isVerified(): bool
    {
        return $this->status === self::STATUS_ACTIVE && $this->verified_at !== null;
    }

    public function isSslActive(): bool
    {
        return $this->ssl_status === self::SSL_STATUS_ACTIVE;
    }

    public function getTxtRecordHost(): string
    {
        return "_affiliate-os-challenge.{$this->domain}";
    }

    public function getTxtRecordValue(): string
    {
        return (string) $this->verification_token;
    }

    public function getCnameTarget(): string
    {
        return (string) config('tenancy.cname_target', 'cname.affiliate-os.com');
    }

    public function getServerIpTarget(): string
    {
        return (string) config('tenancy.server_ip', '127.0.0.1');
    }
}
