<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Account extends Model
{
    use BelongsToTenant;

    protected $table = 'accounts';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'code',
        'owner_type',
        'owner_id',
        'kind',
        'name',
        'currency',
        'balance_minor',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'balance_minor' => 'integer',
        'owner_id' => 'integer',
    ];

    /**
     * @return MorphTo<Model, $this>
     */
    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return HasMany<LedgerLine, $this>
     */
    public function ledgerLines(): HasMany
    {
        return $this->hasMany(LedgerLine::class, 'account_id');
    }

    public function hasDebt(): bool
    {
        return $this->balance_minor < 0;
    }
}
