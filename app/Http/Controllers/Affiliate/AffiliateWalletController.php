<?php

declare(strict_types=1);

namespace App\Http\Controllers\Affiliate;

use App\Domain\Affiliate\Settings\AffiliateProgramSettings;
use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Affiliate;
use App\Models\LedgerLine;
use App\Support\Money;
use App\Support\Translate;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AffiliateWalletController extends Controller
{
    public function index(Request $request): View
    {
        /** @var Affiliate $affiliate */
        $affiliate = Auth::guard('affiliate')->user();

        $accountIds = Account::query()
            ->where('owner_type', Affiliate::class)
            ->where('owner_id', $affiliate->id)
            ->pluck('id');

        $lines = LedgerLine::query()
            ->whereIn('account_id', $accountIds)
            ->with(['journalEntry', 'account'])
            ->latest('id')
            ->paginate(20);

        $settings = AffiliateProgramSettings::load();

        return view('affiliate.wallet.index', [
            'affiliate' => $affiliate,
            'lines' => $lines,
            'settings' => $settings,
            'pendingMinor' => $affiliate->getPendingBalance(),
            'availableMinor' => $affiliate->getAvailableBalance(),
            'lockedMinor' => $affiliate->getLockedBalance(),
            'withdrawnMinor' => $affiliate->getWithdrawnBalance(),
            'hasDebt' => $affiliate->hasDebt(),
            'debtMinor' => $affiliate->getDebtMinor(),
            'canWithdraw' => $affiliate->canWithdraw(),
        ]);
    }

    public function exportCsv(): StreamedResponse
    {
        /** @var Affiliate $affiliate */
        $affiliate = Auth::guard('affiliate')->user();

        $accountIds = Account::query()
            ->where('owner_type', Affiliate::class)
            ->where('owner_id', $affiliate->id)
            ->pluck('id');

        /** @var Collection<int, LedgerLine> $lines */
        $lines = LedgerLine::query()
            ->whereIn('account_id', $accountIds)
            ->with(['journalEntry', 'account'])
            ->latest('id')
            ->get();

        $filename = 'wallet_statement_'.now()->format('Y_m_d_His').'.csv';

        return response()->streamDownload(function () use ($lines) {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                return;
            }

            // UTF-8 BOM for Arabic support in Excel
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));

            fputcsv($handle, [
                Translate::t('معرف الحركة'),
                Translate::t('التاريخ والوقت'),
                Translate::t('الحساب'),
                Translate::t('نوع القيد'),
                Translate::t('البيان'),
                Translate::t('المبلغ (').Money::symbol().')',
            ]);

            foreach ($lines as $line) {
                $accountName = $line->account ? $line->account->name : Translate::t('غير محدد');
                $entryType = $line->journalEntry ? $line->journalEntry->type : '';
                $description = $line->journalEntry ? $line->journalEntry->description : '';
                $amountFormatted = number_format($line->amount_minor / 100, 2, '.', '');

                fputcsv($handle, [
                    $line->id,
                    $line->created_at->format('Y-m-d H:i:s'),
                    $accountName,
                    $entryType,
                    $description,
                    $amountFormatted,
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
