<?php

declare(strict_types=1);

namespace App\Http\Controllers\Affiliate;

use App\Domain\Affiliate\Settings\AffiliateProgramSettings;
use App\Domain\Finance\Services\AccountResolver;
use App\Domain\Finance\Services\PayoutTurnaroundCalculator;
use App\Domain\Orders\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Affiliate;
use App\Models\LedgerLine;
use App\Models\Tenant;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Lunar\Models\Order;

class AffiliateDashboardController extends Controller
{
    public function index(
        AccountResolver $accountResolver,
        PayoutTurnaroundCalculator $turnaroundCalculator
    ): View {
        /** @var Affiliate $affiliate */
        $affiliate = Auth::guard('affiliate')->user();
        $tenant = function_exists('tenancy') && tenancy()->initialized ? tenancy()->tenant : null;
        $settings = AffiliateProgramSettings::load();

        // 1. Balances & Financials
        $availableBalance = $affiliate->getAvailableBalance();
        $pendingBalance = $affiliate->getPendingBalance();
        $lockedBalance = $affiliate->getLockedBalance();
        $withdrawnBalance = $affiliate->getWithdrawnBalance();
        $hasDebt = $affiliate->hasDebt();
        $debtMinor = $affiliate->getDebtMinor();

        // 2. Orders Statistics
        $ordersQuery = Order::query()->where('affiliate_id', $affiliate->id);
        $totalOrders = (clone $ordersQuery)->count();
        $deliveredOrders = (clone $ordersQuery)->where('status', OrderStatus::DELIVERED->value)->count();
        $cancelledOrders = (clone $ordersQuery)->whereIn('status', [
            OrderStatus::CANCELLED_BY_CUSTOMER->value,
            OrderStatus::CANCELLED_NO_ANSWER->value,
        ])->count();
        $returnedOrders = (clone $ordersQuery)->whereIn('status', [
            OrderStatus::RETURN_REQUESTED->value,
            OrderStatus::RETURNING->value,
            OrderStatus::RETURNED->value,
        ])->count();
        $inProgressOrders = $totalOrders - ($deliveredOrders + $cancelledOrders + $returnedOrders);
        $deliveryRate = $totalOrders > 0 ? round(($deliveredOrders / $totalOrders) * 100, 1) : 0.0;

        // 3. Recent Orders
        $recentOrders = (clone $ordersQuery)
            ->latest()
            ->take(6)
            ->get();

        // 4. Recent Wallet Movements
        $affiliateAccountIds = [
            $accountResolver->getAffiliatePendingAccount($affiliate)->id,
            $accountResolver->getAffiliateAvailableAccount($affiliate)->id,
            $accountResolver->getAffiliateLockedAccount($affiliate)->id,
            $accountResolver->getAffiliateWithdrawnAccount($affiliate)->id,
        ];

        $recentMovements = LedgerLine::query()
            ->whereIn('account_id', $affiliateAccountIds)
            ->with(['account', 'journalEntry'])
            ->latest('id')
            ->take(6)
            ->get();

        $averageTurnaround = $turnaroundCalculator->getAverageTurnaroundHuman();

        return view('affiliate.dashboard', [
            'affiliate' => $affiliate,
            'storeName' => $tenant instanceof Tenant ? $tenant->name : 'متجرنا',
            'settings' => $settings,
            'referralLink' => url('/partners/join?ref='.$affiliate->code),
            'availableBalance' => $availableBalance,
            'pendingBalance' => $pendingBalance,
            'lockedBalance' => $lockedBalance,
            'withdrawnBalance' => $withdrawnBalance,
            'hasDebt' => $hasDebt,
            'debtMinor' => $debtMinor,
            'totalOrders' => $totalOrders,
            'deliveredOrders' => $deliveredOrders,
            'cancelledOrders' => $cancelledOrders,
            'returnedOrders' => $returnedOrders,
            'inProgressOrders' => $inProgressOrders,
            'deliveryRate' => $deliveryRate,
            'recentOrders' => $recentOrders,
            'recentMovements' => $recentMovements,
            'averageTurnaround' => $averageTurnaround,
        ]);
    }
}
