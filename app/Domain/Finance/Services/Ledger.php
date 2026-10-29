<?php

declare(strict_types=1);

namespace App\Domain\Finance\Services;

use App\Domain\Finance\DTOs\EntryData;
use App\Domain\Finance\Exceptions\UnbalancedLedgerEntryException;
use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\LedgerLine;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class Ledger
{
    /**
     * Post a balanced double-entry journal entry to the ledger.
     *
     * @throws UnbalancedLedgerEntryException
     */
    public function post(EntryData $data): JournalEntry
    {
        // 1. Verify zero-sum invariant
        $totalSum = 0;
        foreach ($data->lines as $line) {
            $totalSum += $line->amountMinor;
        }

        if ($totalSum !== 0) {
            throw new UnbalancedLedgerEntryException($totalSum);
        }

        $tenantId = tenant('id') ? (string) tenant('id') : 'central';

        // 2. Idempotency Check (Fast path)
        /** @var JournalEntry|null $existing */
        $existing = JournalEntry::query()
            ->where('tenant_id', $tenantId)
            ->where('idempotency_key', $data->idempotencyKey)
            ->with(['lines.account'])
            ->first();

        if ($existing instanceof JournalEntry) {
            return $existing;
        }

        // 3. Execute Posting in a Transaction with Deadlock Retries (up to 3 attempts)
        return DB::transaction(function () use ($data, $tenantId) {
            // Re-check idempotency under transaction lock
            /** @var JournalEntry|null $lockedExisting */
            $lockedExisting = JournalEntry::query()
                ->where('tenant_id', $tenantId)
                ->where('idempotency_key', $data->idempotencyKey)
                ->with(['lines.account'])
                ->first();

            if ($lockedExisting instanceof JournalEntry) {
                return $lockedExisting;
            }

            // Extract unique account IDs and sort ASCENDING to guarantee deterministic lock order (prevents deadlocks)
            $accountDeltas = [];
            foreach ($data->lines as $line) {
                $accId = (int) $line->account->id;
                $accountDeltas[$accId] = ($accountDeltas[$accId] ?? 0) + $line->amountMinor;
            }

            $sortedAccountIds = array_keys($accountDeltas);
            sort($sortedAccountIds, SORT_NUMERIC);

            // Lock accounts in strict ascending ID order
            /** @var Collection<int, Account> $lockedAccounts */
            $lockedAccounts = Account::query()
                ->whereIn('id', $sortedAccountIds)
                ->orderBy('id', 'asc')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            // Create Journal Entry
            /** @var JournalEntry $journalEntry */
            $journalEntry = JournalEntry::create([
                'tenant_id' => $tenantId,
                'type' => $data->type,
                'idempotency_key' => $data->idempotencyKey,
                'ref_type' => $data->refType,
                'ref_id' => $data->refId,
                'description' => $data->description,
                'created_by' => $data->createdBy,
            ]);

            // Create Ledger Lines & update balances
            foreach ($data->lines as $line) {
                LedgerLine::create([
                    'tenant_id' => $tenantId,
                    'journal_entry_id' => $journalEntry->id,
                    'account_id' => $line->account->id,
                    'amount_minor' => $line->amountMinor,
                ]);
            }

            // Apply deltas to locked accounts (AF-18)
            foreach ($accountDeltas as $accountId => $deltaMinor) {
                $account = $lockedAccounts->get($accountId);
                if (! $account instanceof Account) {
                    throw new \RuntimeException("Account ID {$accountId} could not be locked for balance update.");
                }
                $account->increment('balance_minor', $deltaMinor);
            }

            return $journalEntry->load('lines.account');
        }, 3);
    }
}
