<?php

declare(strict_types=1);

namespace App\Http\Controllers\Store;

use App\Domain\Customer\Contracts\CustomerWalletServiceInterface;
use App\Domain\Customer\Services\CustomerLoyaltyService;
use App\Http\Controllers\Controller;
use App\Models\CustomerLoyaltyAccount;
use App\Models\CustomerWallet;
use App\Models\CustomerWalletTransaction;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StoreCustomerWalletController extends Controller
{
    public function index(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();
        $tenantId = $user->tenant_id;

        $search = $request->query('search');

        $query = CustomerWallet::query()
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->with(['customer.addresses'])
            ->orderByDesc('balance_minor');

        if ($search) {
            $query->whereHas('customer', function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $wallets = $query->paginate(20)->withQueryString();

        $stats = [
            'total_wallets' => CustomerWallet::query()->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))->count(),
            'total_balance_minor' => (int) CustomerWallet::query()->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))->sum('balance_minor'),
            'total_loyalty_points' => (int) CustomerLoyaltyAccount::query()->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))->sum('points_balance'),
        ];

        return view('store.wallets.index', [
            'wallets' => $wallets,
            'stats' => $stats,
            'search' => $search,
        ]);
    }

    public function show(CustomerWallet $wallet, CustomerLoyaltyService $loyaltyService): View
    {
        $wallet->load(['customer', 'transactions' => fn ($q) => $q->orderByDesc('id')]);

        $loyaltyAccount = $wallet->customer_id ? $loyaltyService->getAccount($wallet->customer_id) : null;
        if ($loyaltyAccount) {
            $loyaltyAccount->load(['transactions' => fn ($q) => $q->orderByDesc('id')]);
        }

        return view('store.wallets.show', [
            'wallet' => $wallet,
            'loyaltyAccount' => $loyaltyAccount,
        ]);
    }

    public function adjustWallet(
        Request $request,
        CustomerWallet $wallet,
        CustomerWalletServiceInterface $walletService
    ): RedirectResponse {
        /** @var User|null $user */
        $user = $request->user();
        if (! $user) {
            abort(401);
        }

        if ($user->hasRole('call_center') || $user->hasRole('orders')) {
            abort(403, 'تعديل رصيد المحفظة مقتصر على الأدوار المالية والإدارية المعتمدة فقط.');
        }

        if ($user->roles()->exists() && ! $user->is_platform_admin && ! $user->hasAnyRole(['owner', 'manager', 'finance', 'admin'])) {
            abort(403, 'تعديل رصيد المحفظة مقتصر على الأدوار المالية والإدارية المعتمدة فقط.');
        }

        $tenantId = tenant('id') ? (string) tenant('id') : null;
        if ($tenantId && $wallet->tenant_id !== $tenantId) {
            abort(404);
        }

        $validated = $request->validate([
            'action' => ['required', 'in:credit,debit'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:10000'],
            'notes' => ['required', 'string', 'max:500'],
        ]);

        $amountMinor = (int) round(((float) $validated['amount']) * 100);
        $action = $validated['action'];
        $notes = (string) $validated['notes'];

        if ($action === 'credit') {
            $walletService->credit(
                customer: $wallet->customer_id,
                amountMinor: $amountMinor,
                type: CustomerWalletTransaction::TYPE_ADJUSTMENT,
                referenceId: 'manual_adj_'.uniqid(),
                notes: "تعديل يدوي من إدارة المتجر: {$notes}"
            );
            $msg = 'تمت إضافة '.number_format($amountMinor / 100, 2).' ج.م إلى محفظة العميل بنجاح.';
        } else {
            $walletService->debit(
                customer: $wallet->customer_id,
                amountMinor: $amountMinor,
                type: CustomerWalletTransaction::TYPE_ADJUSTMENT,
                referenceId: 'manual_adj_'.uniqid(),
                notes: "خصم يدوي من إدارة المتجر: {$notes}"
            );
            $msg = 'تم خصم '.number_format($amountMinor / 100, 2).' ج.م من محفظة العميل بنجاح.';
        }

        return redirect()->route('store.wallets.show', $wallet)
            ->with('success', $msg);
    }

    public function adjustLoyalty(
        Request $request,
        CustomerWallet $wallet,
        CustomerLoyaltyService $loyaltyService
    ): RedirectResponse {
        /** @var User|null $user */
        $user = $request->user();
        if (! $user) {
            abort(401);
        }

        if ($user->hasRole('call_center') || $user->hasRole('orders')) {
            abort(403, 'تعديل نقاط الولاء مقتصر على الأدوار المالية والإدارية المعتمدة فقط.');
        }

        if ($user->roles()->exists() && ! $user->is_platform_admin && ! $user->hasAnyRole(['owner', 'manager', 'finance', 'admin'])) {
            abort(403, 'تعديل نقاط الولاء مقتصر على الأدوار المالية والإدارية المعتمدة فقط.');
        }

        $tenantId = tenant('id') ? (string) tenant('id') : null;
        if ($tenantId && $wallet->tenant_id !== $tenantId) {
            abort(404);
        }

        $validated = $request->validate([
            'action' => ['required', 'in:award,deduct'],
            'points' => ['required', 'integer', 'min:1', 'max:50000'],
            'notes' => ['required', 'string', 'max:500'],
        ]);

        $points = (int) $validated['points'];
        $action = $validated['action'];
        $notes = (string) $validated['notes'];

        $account = $loyaltyService->getAccount($wallet->customer_id);

        if ($action === 'award') {
            $account->award(
                points: $points,
                type: 'adjustment',
                notes: "تعديل يدوي بالزيادة من إدارة المتجر: {$notes}"
            );
            $msg = "تم منح {$points} نقطة ولاء للعميل بنجاح.";
        } else {
            $account->redeem(
                points: $points,
                type: 'adjustment',
                notes: "خصم نقاط يدوي من إدارة المتجر: {$notes}"
            );
            $msg = "تم خصم {$points} نقطة ولاء من حساب العميل بنجاح.";
        }

        return redirect()->route('store.wallets.show', $wallet)
            ->with('success', $msg);
    }
}
