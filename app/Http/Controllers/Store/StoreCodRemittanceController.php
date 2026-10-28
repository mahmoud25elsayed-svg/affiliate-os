<?php

declare(strict_types=1);

namespace App\Http\Controllers\Store;

use App\Domain\Finance\DTOs\RemittanceImportRow;
use App\Domain\Finance\Enums\CodRemittanceStatus;
use App\Domain\Finance\Services\CodRemittanceService;
use App\Domain\Finance\Services\CodSettlementService;
use App\Http\Controllers\Controller;
use App\Models\CodRemittance;
use App\Models\CourierAccount;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StoreCodRemittanceController extends Controller
{
    public function index(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();
        $tenantId = $user->tenant_id;

        $status = $request->query('status');

        $query = CodRemittance::query()
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->with(['courierAccount', 'settler'])
            ->orderByDesc('id');

        if ($status && CodRemittanceStatus::tryFrom((string) $status)) {
            $query->where('status', $status);
        }

        $remittances = $query->paginate(20)->withQueryString();

        $stats = [
            'total_count' => CodRemittance::query()->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))->count(),
            'pending_settlement' => CodRemittance::query()->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))->whereIn('status', [CodRemittanceStatus::RECONCILED->value, CodRemittanceStatus::DISCREPANT->value])->count(),
            'settled_count' => CodRemittance::query()->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))->where('status', CodRemittanceStatus::SETTLED->value)->count(),
            'total_settled_minor' => (int) CodRemittance::query()->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))->where('status', CodRemittanceStatus::SETTLED->value)->sum('net_payout_minor'),
        ];

        return view('store.cod.index', [
            'remittances' => $remittances,
            'stats' => $stats,
            'currentStatus' => $status,
            'statuses' => CodRemittanceStatus::cases(),
        ]);
    }

    public function create(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();
        $tenantId = $user->tenant_id;

        $courierAccounts = CourierAccount::query()
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->where('is_active', true)
            ->get();

        return view('store.cod.create', [
            'courierAccounts' => $courierAccounts,
        ]);
    }

    public function store(Request $request, CodRemittanceService $remittanceService): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $tenantId = $user->tenant_id;

        $validated = $request->validate([
            'courier_account_id' => ['required', 'exists:courier_accounts,id'],
            'reference' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'raw_csv' => ['nullable', 'string'],
        ]);

        /** @var CourierAccount $account */
        $account = CourierAccount::query()
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->findOrFail($validated['courier_account_id']);

        $rows = [];

        // Parse CSV or JSON input
        if (! empty($validated['raw_csv'])) {
            $lines = explode("\n", trim($validated['raw_csv']));
            foreach ($lines as $line) {
                $line = trim($line);
                if (empty($line) || str_starts_with($line, '#')) {
                    continue;
                }

                // Expected format: tracking_number,cod_amount,shipping_fee,status
                $parts = str_getcsv($line);
                if (! empty($parts[0])) {
                    $rows[] = RemittanceImportRow::fromArray([
                        'tracking_number' => $parts[0],
                        'reported_cod_minor' => isset($parts[1]) ? (int) (round((float) $parts[1] * 100)) : 0,
                        'shipping_fee_minor' => isset($parts[2]) ? (int) (round((float) $parts[2] * 100)) : 0,
                        'status' => $parts[3] ?? 'delivered',
                    ]);
                }
            }
        }

        if (empty($rows) && $request->has('items') && is_array($request->input('items'))) {
            /** @var array<int, array<string, mixed>> $items */
            $items = $request->input('items');
            foreach ($items as $item) {
                if (! empty($item['tracking_number'])) {
                    $rows[] = RemittanceImportRow::fromArray($item);
                }
            }
        }

        $remittance = $remittanceService->createRemittance(
            courierAccount: $account,
            rows: $rows,
            reference: ! empty($validated['reference']) ? (string) $validated['reference'] : null,
            creator: $user,
            notes: ! empty($validated['notes']) ? (string) $validated['notes'] : null
        );

        return redirect()->route('store.cod.show', $remittance)
            ->with('success', "تم استيراد ومطابقة كشف التسوية #{$remittance->reference} بنجاح.");
    }

    public function show(CodRemittance $remittance): View
    {
        $remittance->load(['courierAccount', 'creator', 'settler', 'items.order', 'items.shipment']);

        return view('store.cod.show', [
            'remittance' => $remittance,
        ]);
    }

    public function settle(Request $request, CodRemittance $remittance, CodSettlementService $settlementService): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $validated = $request->validate([
            'bank_reference' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $settlementService->settleRemittance(
            remittance: $remittance,
            settler: $user,
            bankReference: $validated['bank_reference'] ?? null,
            notes: $validated['notes'] ?? null
        );

        return redirect()->route('store.cod.show', $remittance)
            ->with('success', "تم تأكيد تسوية وتوريد الكشف #{$remittance->reference} بنجاح وترحيل القيود المالية.");
    }
}
