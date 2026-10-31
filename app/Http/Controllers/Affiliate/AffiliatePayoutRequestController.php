<?php

declare(strict_types=1);

namespace App\Http\Controllers\Affiliate;

use App\Domain\Affiliate\Settings\AffiliateProgramSettings;
use App\Domain\Finance\Exceptions\InsufficientAffiliateBalanceException;
use App\Domain\Finance\Exceptions\PayoutNotEligibleException;
use App\Domain\Finance\Services\PayoutService;
use App\Domain\Finance\Services\PayoutTurnaroundCalculator;
use App\Http\Controllers\Controller;
use App\Models\Affiliate;
use App\Models\PayoutRequest;
use App\Models\Tenant;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AffiliatePayoutRequestController extends Controller
{
    public function __construct(
        protected PayoutService $payoutService,
        protected PayoutTurnaroundCalculator $turnaroundCalculator
    ) {}

    public function index(): View
    {
        /** @var Affiliate $affiliate */
        $affiliate = Auth::guard('affiliate')->user();
        $tenant = function_exists('tenancy') && tenancy()->initialized ? tenancy()->tenant : null;
        $settings = AffiliateProgramSettings::load();

        $payoutRequests = PayoutRequest::query()
            ->where('affiliate_id', $affiliate->id)
            ->latest('id')
            ->paginate(15);

        return view('affiliate.payouts.index', [
            'affiliate' => $affiliate,
            'storeName' => $tenant instanceof Tenant ? $tenant->name : 'متجرنا',
            'settings' => $settings,
            'payoutRequests' => $payoutRequests,
            'availableBalance' => $affiliate->getAvailableBalance(),
            'lockedBalance' => $affiliate->getLockedBalance(),
            'withdrawnBalance' => $affiliate->getWithdrawnBalance(),
            'hasDebt' => $affiliate->hasDebt(),
            'debtMinor' => $affiliate->getDebtMinor(),
            'canWithdraw' => $affiliate->canWithdraw(),
            'averageTurnaround' => $this->turnaroundCalculator->getAverageTurnaroundHuman(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        /** @var Affiliate $affiliate */
        $affiliate = Auth::guard('affiliate')->user();
        $settings = AffiliateProgramSettings::load();

        $request->validate([
            'amount' => ['required', 'numeric', 'min:'.($settings->min_payout_minor / 100)],
        ], [
            'amount.required' => 'يرجى إدخال مبلغ السحب المطلوب',
            'amount.numeric' => 'مبلغ السحب يجب أن يكون رقماً صحيحاً',
            'amount.min' => 'الحد الأدنى لطلب السحب هو '.Money::show($settings->min_payout_minor),
        ]);

        $amountMinor = (int) round(((float) $request->input('amount')) * 100);

        try {
            $this->payoutService->requestPayout(
                affiliate: $affiliate,
                amountMinor: $amountMinor
            );

            return redirect()->route('affiliate.payouts.index')
                ->with('success', 'تم تقديم طلب السحب بنجاح وحجز المبلغ من رصيدك المتاح، وسيتم تحويله في موعد الدفع القادم.');
        } catch (InsufficientAffiliateBalanceException|PayoutNotEligibleException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function cancel(int $id): RedirectResponse
    {
        /** @var Affiliate $affiliate */
        $affiliate = Auth::guard('affiliate')->user();

        /** @var PayoutRequest $payoutRequest */
        $payoutRequest = PayoutRequest::where('affiliate_id', $affiliate->id)->findOrFail($id);

        try {
            $this->payoutService->cancelPayout($payoutRequest, $affiliate);

            return redirect()->route('affiliate.payouts.index')
                ->with('success', 'تم إلغاء طلب السحب بنجاح واسترجاع المبلغ إلى رصيدك المتاح.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
