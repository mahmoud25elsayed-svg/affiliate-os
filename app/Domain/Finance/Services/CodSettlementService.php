<?php

declare(strict_types=1);

namespace App\Domain\Finance\Services;

use App\Domain\Finance\DTOs\EntryData;
use App\Domain\Finance\DTOs\LedgerLineData;
use App\Domain\Finance\Enums\CodRemittanceStatus;
use App\Domain\Finance\Enums\RemittanceItemMatchStatus;
use App\Domain\Orders\DTOs\OrderActor;
use App\Domain\Orders\Enums\OrderStatus;
use App\Domain\Orders\Services\OrderTransitionService;
use App\Models\CodRemittance;
use App\Models\CodRemittanceItem;
use App\Models\User;
use DomainException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Lunar\Models\Order;

class CodSettlementService
{
    public function __construct(
        protected Ledger $ledger,
        protected AccountResolver $accountResolver,
        protected OrderTransitionService $transitionService,
        protected CommissionSettlementService $commissionSettlementService
    ) {}

    /**
     * Settle a remittance sheet, transition matched orders to SETTLED, release affiliate commissions,
     * and record balanced double-entry financial ledger journal entries.
     */
    public function settleRemittance(
        CodRemittance $remittance,
        User $settler,
        ?string $bankReference = null,
        ?string $notes = null
    ): CodRemittance {
        if ($remittance->status === CodRemittanceStatus::SETTLED) {
            throw new DomainException('كشف التسوية هذا تمت تسويته مسبقاً.');
        }

        if ($remittance->status === CodRemittanceStatus::CANCELLED) {
            throw new DomainException('لا يمكن تسوية كشف ملغي.');
        }

        return DB::transaction(function () use ($remittance, $settler, $bankReference, $notes): CodRemittance {
            $actor = OrderActor::staff($settler, role: 'finance');
            $now = Carbon::now();

            // 1. Process matched items & transition orders
            /** @var CodRemittanceItem $item */
            foreach ($remittance->items()->with('order')->get() as $item) {
                if ($item->match_status !== RemittanceItemMatchStatus::MATCHED && $item->match_status !== RemittanceItemMatchStatus::AMOUNT_MISMATCH) {
                    continue;
                }

                $order = $item->order;
                if (! $order instanceof Order) {
                    continue;
                }

                $currentStatus = OrderStatus::tryFrom((string) $order->status);

                // Transition DELIVERED -> SETTLED
                if ($currentStatus === OrderStatus::DELIVERED) {
                    $this->transitionService->transition(
                        order: $order,
                        to: OrderStatus::SETTLED,
                        reason: null,
                        actor: $actor,
                        meta: [
                            'remittance_id' => $remittance->id,
                            'remittance_reference' => $remittance->reference,
                            'bank_reference' => $bankReference,
                        ],
                        note: "تمت تسوية التحصيل والتوريد من شركة الشحن عبر كشف رقم {$remittance->reference}."
                    );

                    // Settle affiliate commission (pending -> available)
                    $this->commissionSettlementService->settleOrder($order);
                }
            }

            // 2. Post Financial Ledger Entry
            $bankAccount = $this->accountResolver->getStoreBankAccount();
            $shippingExpenseAccount = $this->accountResolver->getStoreShippingExpenseAccount();
            $codReceivableAccount = $this->accountResolver->getPlatformCodReceivableAccount();
            $discrepancyAccount = $this->accountResolver->getStoreDiscrepancyExpenseAccount();

            $netPayoutMinor = $remittance->net_payout_minor;
            $shippingFeesMinor = $remittance->total_shipping_fees_minor;
            $collectedMinor = $remittance->total_collected_minor;

            $lines = [];

            if ($netPayoutMinor > 0) {
                $lines[] = new LedgerLineData(account: $bankAccount, amountMinor: $netPayoutMinor);
            }

            if ($shippingFeesMinor > 0) {
                $lines[] = new LedgerLineData(account: $shippingExpenseAccount, amountMinor: $shippingFeesMinor);
            }

            if ($collectedMinor > 0) {
                $lines[] = new LedgerLineData(account: $codReceivableAccount, amountMinor: -$collectedMinor);
            }

            // Verify and balance any mathematical variance
            $sum = 0;
            foreach ($lines as $line) {
                $sum += $line->amountMinor;
            }

            if ($sum !== 0) {
                $lines[] = new LedgerLineData(account: $discrepancyAccount, amountMinor: -$sum);
            }

            if (! empty($lines)) {
                $entryData = new EntryData(
                    type: 'cod_remittance_settled',
                    idempotencyKey: "cod_remittance_settled_{$remittance->id}",
                    description: "تسوية وتوريد كشف متحصلات الشحن رقم {$remittance->reference}",
                    lines: $lines,
                    refType: CodRemittance::class,
                    refId: $remittance->id,
                    createdBy: $settler->id
                );

                $this->ledger->post($entryData);
            }

            // 3. Mark Remittance as SETTLED
            $remittance->update([
                'status' => CodRemittanceStatus::SETTLED,
                'settled_at' => $now,
                'settled_by' => $settler->id,
                'bank_reference' => $bankReference ?? $remittance->bank_reference,
                'notes' => $notes ?? $remittance->notes,
            ]);

            return $remittance->fresh() ?? $remittance;
        });
    }
}
