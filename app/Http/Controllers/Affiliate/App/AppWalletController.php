<?php

declare(strict_types=1);

namespace App\Http\Controllers\Affiliate\App;

use App\Domain\Affiliate\Settings\AffiliateProgramSettings;
use App\Domain\Finance\Exceptions\InsufficientAffiliateBalanceException;
use App\Domain\Finance\Exceptions\PayoutNotEligibleException;
use App\Domain\Finance\Services\AccountResolver;
use App\Domain\Finance\Services\PayoutService;
use App\Domain\Finance\Services\PayoutTurnaroundCalculator;
use App\Http\Controllers\Controller;
use App\Models\Affiliate;
use App\Models\LedgerLine;
use App\Models\PayoutRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class AppWalletController extends Controller
{
    public function index(
        AccountResolver $accountResolver,
        PayoutTurnaroundCalculator $turnaroundCalculator
    ): Response {
        /** @var Affiliate $affiliate */
        $affiliate = Auth::guard('affiliate')->user();
        $settings = AffiliateProgramSettings::load();

        $accountIds = [
            $accountResolver->getAffiliatePendingAccount($affiliate)->id,
            $accountResolver->getAffiliateAvailableAccount($affiliate)->id,
            $accountResolver->getAffiliateLockedAccount($affiliate)->id,
            $accountResolver->getAffiliateWithdrawnAccount($affiliate)->id,
        ];

        $movements = LedgerLine::query()
            ->whereIn('account_id', $accountIds)
            ->with(['account', 'journalEntry'])
            ->latest('id')
            ->paginate(15)
            ->through(fn ($line) => [
                'id' => $line->id,
                'account_name' => $line->account ? $line->account->name : 'حساب',
                'description' => $line->journalEntry?->description ?: $line->journalEntry?->type,
                'amount_minor' => $line->amount_minor,
                'created_at' => $line->created_at->format('Y-m-d H:i'),
            ]);

        $payoutRequests = PayoutRequest::query()
            ->where('affiliate_id', $affiliate->id)
            ->latest('id')
            ->take(10)
            ->get()
            ->map(fn ($req) => [
                'id' => $req->id,
                'amount_minor' => $req->amount_minor,
                'status' => $req->status,
                'payment_method' => $req->payment_method,
                'reference_number' => $req->reference_number,
                'rejection_reason' => $req->rejection_reason,
                'created_at' => $req->created_at->format('Y-m-d H:i'),
            ]);

        return Inertia::render('App/Wallet', [
            'balances' => [
                'available' => $affiliate->getAvailableBalance(),
                'pending' => $affiliate->getPendingBalance(),
                'locked' => $affiliate->getLockedBalance(),
                'withdrawn' => $affiliate->getWithdrawnBalance(),
                'debt' => $affiliate->getDebtMinor(),
                'has_debt' => $affiliate->hasDebt(),
            ],
            'eligibility' => [
                'can_withdraw' => $affiliate->canWithdraw(),
                'is_frozen' => $affiliate->isPayoutFrozen(),
                'frozen_until' => $affiliate->payouts_frozen_until?->format('Y-m-d H:i'),
                'has_kyc' => $affiliate->hasApprovedKyc(),
                'min_payout_minor' => $settings->min_payout_minor,
            ],
            'averageTurnaround' => $turnaroundCalculator->getAverageTurnaroundHuman(),
            'movements' => $movements,
            'payoutRequests' => $payoutRequests,
            'payoutMethod' => $affiliate->payout_method,
            'payoutDetails' => $affiliate->payout_details,
        ]);
    }

    public function requestPayout(Request $request, PayoutService $payoutService): RedirectResponse
    {
        /** @var Affiliate $affiliate */
        $affiliate = Auth::guard('affiliate')->user();
        $settings = AffiliateProgramSettings::load();

        $request->validate([
            'amount_minor' => ['required', 'integer', 'min:'.$settings->min_payout_minor],
        ]);

        try {
            $payoutService->requestPayout(
                affiliate: $affiliate,
                amountMinor: (int) $request->input('amount_minor')
            );

            return redirect()->route('affiliate.app.wallet')
                ->with('success', 'تم تقديم طلب السحب بنجاح وحجز الرصيد بالدفتر المالي.');
        } catch (InsufficientAffiliateBalanceException|PayoutNotEligibleException $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
