<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Finance\Exceptions\ImmutableFinancialRecordException;
use App\Support\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LedgerLine extends Model
{
    use BelongsToTenant;

    public const UPDATED_AT = null;

    protected $table = 'ledger_lines';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'journal_entry_id',
        'account_id',
        'amount_minor',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'amount_minor' => 'integer',
        'journal_entry_id' => 'integer',
        'account_id' => 'integer',
    ];

    protected static function booted(): void
    {
        static::updating(function () {
            throw new ImmutableFinancialRecordException('لا يمكن تعديل السطور الدفترية المالية.');
        });

        static::deleting(function () {
            throw new ImmutableFinancialRecordException('لا يمكن حذف السطور الدفترية المالية.');
        });
    }

    /**
     * @return BelongsTo<JournalEntry, $this>
     */
    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }

    /**
     * @return BelongsTo<Account, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'account_id');
    }
}
