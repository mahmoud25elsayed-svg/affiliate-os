<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Account;
use App\Models\LedgerLine;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LedgerReconcileCommand extends Command
{
    protected $signature = 'ledger:reconcile';

    protected $description = 'Perform nightly audit and reconciliation of ledger lines against account balances';

    public function handle(): int
    {
        $this->info('بدء فحص ومطابقة القيود المحاسبية والدفاتر المالية...');

        $discrepancies = [];

        // 1. Audit Invariant: Each Journal Entry sum must equal 0
        /** @var Collection<int, object{journal_entry_id: int, total: int}> $unbalancedEntries */
        $unbalancedEntries = LedgerLine::query()
            ->select('journal_entry_id', DB::raw('SUM(amount_minor) as total'))
            ->groupBy('journal_entry_id')
            ->havingRaw('SUM(amount_minor) != 0')
            ->get();

        if ($unbalancedEntries->isNotEmpty()) {
            foreach ($unbalancedEntries as $unbalanced) {
                $msg = "خلل محاسبي حرج: القيد رقم #{$unbalanced->journal_entry_id} غير متوازن بمبلغ {$unbalanced->total} قرش!";
                $this->error($msg);
                Log::critical($msg);
                $discrepancies[] = $msg;
            }
        }

        // 2. Audit Invariant: Account balance_minor must match sum of ledger lines
        /** @var \Illuminate\Database\Eloquent\Collection<int, Account> $accounts */
        $accounts = Account::all();
        $accountMismatches = 0;

        foreach ($accounts as $account) {
            $computedSum = (int) LedgerLine::where('account_id', $account->id)->sum('amount_minor');
            if ($computedSum !== (int) $account->balance_minor) {
                $accountMismatches++;
                $diff = (int) $account->balance_minor - $computedSum;
                $msg = "فارق رصيد في الحساب #{$account->id} ({$account->code}): المسجل={$account->balance_minor}، مجموع السطور={$computedSum}، الفارق={$diff} قرش.";
                $this->error($msg);
                Log::critical($msg);
                $discrepancies[] = $msg;
            }
        }

        if (count($discrepancies) > 0) {
            $this->error('فشلت المطابقة المحاسبية! تم العثور على '.count($discrepancies).' فروقات أو قيود غير متوازنة.');

            return self::FAILURE;
        }

        $this->info('المطابقة المحاسبية سليمة 100%! فُحص '.count($accounts).' حساباً وجميع الأرصدة متطابقة تماماً مع مجموع السطور.');

        return self::SUCCESS;
    }
}
