<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Finance\Exceptions\ImmutableFinancialRecordException;
use App\Support\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class JournalEntry extends Model
{
    use BelongsToTenant;

    public const UPDATED_AT = null;

    protected $table = 'journal_entries';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'type',
        'idempotency_key',
        'ref_type',
        'ref_id',
        'description',
        'created_by',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'ref_id' => 'integer',
        'created_by' => 'integer',
    ];

    protected static function booted(): void
    {
        static::updating(function () {
            throw new ImmutableFinancialRecordException(__('لا يمكن تعديل القيود المالية المسجلة في اليومية.'));
        });

        static::deleting(function () {
            throw new ImmutableFinancialRecordException(__('لا يمكن حذف القيود المالية المسجلة في اليومية.'));
        });
    }

    /**
     * @return HasMany<LedgerLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(LedgerLine::class, 'journal_entry_id');
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function ref(): MorphTo
    {
        return $this->morphTo();
    }
}
