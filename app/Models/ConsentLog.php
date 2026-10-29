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
 * @property int|null $user_id
 * @property string $subject_type
 * @property string $subject_identifier
 * @property string $document_type
 * @property string $version
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property array<string, mixed>|null $metadata
 * @property Carbon $accepted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Tenant $tenant
 * @property-read User|null $user
 */
class ConsentLog extends Model
{
    use BelongsToTenant;

    public const DOCUMENT_MERCHANT_TERMS = 'merchant_terms';

    public const DOCUMENT_DPA = 'dpa';

    public const DOCUMENT_AFFILIATE_TERMS = 'affiliate_terms';

    public const DOCUMENT_PRIVACY_POLICY = 'privacy_policy';

    public const DOCUMENT_SHIPPING_RETURNS = 'shipping_returns';

    public const DOCUMENT_AUP = 'aup';

    public const SUBJECT_MERCHANT = 'merchant';

    public const SUBJECT_AFFILIATE = 'affiliate';

    public const SUBJECT_CUSTOMER = 'customer';

    protected $table = 'consent_logs';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'user_id',
        'subject_type',
        'subject_identifier',
        'document_type',
        'version',
        'ip_address',
        'user_agent',
        'metadata',
        'accepted_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'accepted_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
