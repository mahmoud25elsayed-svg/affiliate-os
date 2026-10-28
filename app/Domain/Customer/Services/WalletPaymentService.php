<?php

declare(strict_types=1);

namespace App\Domain\Customer\Services;

use App\Domain\Customer\Contracts\CustomerWalletServiceInterface;
use App\Domain\Finance\DTOs\EntryData;
use App\Domain\Finance\DTOs\LedgerLineData;
use App\Domain\Finance\Services\AccountResolver;
use App\Domain\Finance\Services\Ledger;
use App\Models\Customer as AppCustomer;
use App\Models\CustomerWalletTransaction;
use DomainException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Lunar\DataTypes\Price;
use Lunar\Models\Customer as LunarCustomer;
use Lunar\Models\Order;

class WalletPaymentService
{
    public function __construct(
        protected CustomerWalletServiceInterface $walletService,
        protected Ledger $ledger,
        protected AccountResolver $accountResolver
    ) {}

    /**
     * Deduct customer wallet balance towards paying an order, updating order snapshot and posting ledger entry.
     */
    public function applyWalletBalance(
        Order $order,
        AppCustomer|LunarCustomer|int $customer,
        int $requestedAmountMinor
    ): CustomerWalletTransaction {
        if ($requestedAmountMinor <= 0) {
            throw new InvalidArgumentException('مبلغ الخصم من المحفظة يجب أن يكون أكبر من الصفر.');
        }

        $customerId = is_int($customer) ? $customer : (int) $customer->id;
        $availableBalance = $this->walletService->getBalance($customerId);

        if ($requestedAmountMinor > $availableBalance) {
            throw new DomainException(sprintf(
                'رصيد المحفظة غير كافٍ. المتاح: %s ج.م، المطلوب: %s ج.م',
                number_format($availableBalance / 100, 2),
                number_format($requestedAmountMinor / 100, 2)
            ));
        }

        /** @var mixed $rawTotal */
        $rawTotal = $order->total;
        $orderTotalMinor = (int) ($rawTotal instanceof Price ? $rawTotal->value : $rawTotal);

        $payableMinor = min($requestedAmountMinor, $orderTotalMinor);

        return DB::transaction(function () use ($order, $customer, $customerId, $payableMinor): CustomerWalletTransaction {
            // Debit customer wallet
            $tx = $this->walletService->debit(
                customer: $customer,
                amountMinor: $payableMinor,
                type: CustomerWalletTransaction::TYPE_PURCHASE,
                referenceId: (string) $order->id,
                notes: "سداد قيمة الطلب #{$order->reference} من رصيد المحفظة"
            );

            // Update order price snapshot
            $rawSnapshot = $order->getAttribute('price_snapshot');
            $snapshot = is_string($rawSnapshot)
                ? (json_decode($rawSnapshot, true) ?? [])
                : (is_array($rawSnapshot) ? $rawSnapshot : []);

            $snapshot['wallet_paid_minor'] = $payableMinor;
            $snapshot['remaining_due_minor'] = max(0, ((int) ($snapshot['customer_total_minor'] ?? $order->total)) - $payableMinor);

            $order->update([
                'price_snapshot' => json_encode($snapshot),
            ]);

            // Post financial ledger entry
            /** @var LunarCustomer|null $lunarCustomer */
            $lunarCustomer = LunarCustomer::find($customerId);
            if ($lunarCustomer instanceof LunarCustomer) {
                $walletAccount = $this->accountResolver->getCustomerWalletAccount($lunarCustomer);
                $revenueAccount = $this->accountResolver->getStoreRevenueAccount();

                $entryData = new EntryData(
                    type: 'wallet_purchase_payment',
                    idempotencyKey: "wallet_payment_order_{$order->id}_{$tx->id}",
                    description: "سداد من محفظة العميل للطلب #{$order->reference} بمبلغ ".number_format($payableMinor / 100, 2).' ج.م',
                    lines: [
                        new LedgerLineData(account: $revenueAccount, amountMinor: $payableMinor),
                        new LedgerLineData(account: $walletAccount, amountMinor: -$payableMinor),
                    ],
                    refType: Order::class,
                    refId: (int) $order->id,
                    createdBy: null
                );

                $this->ledger->post($entryData);
            }

            return $tx;
        });
    }
}
