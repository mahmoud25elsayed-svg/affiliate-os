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
 * @property string $type
 * @property string $value
 * @property string|null $reason
 * @property int|null $added_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $author
 */
class MerchantBlacklist extends Model
{
    use BelongsToTenant;

    public const TYPE_PHONE = 'phone';

    public const TYPE_NAME = 'name';

    public const TYPE_IP = 'ip';

    protected $fillable = [
        'tenant_id',
        'type',
        'value',
        'reason',
        'added_by',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by');
    }
}
