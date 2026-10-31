<?php

declare(strict_types=1);

namespace App\Domain\Customer\Services;

use App\Domain\Customer\Contracts\CustomerWalletServiceInterface;
use App\Domain\Finance\DTOs\EntryData;
use App\Domain\Finance\DTOs\LedgerLineData;
use App\Domain\Finance\Services\AccountResolver;
use App\Domain\Finance\Services\Ledger;
use App\Models\Customer as AppCustomer;
use App\Models\CustomerLoyaltyAccount;
use App\Models\CustomerLoyaltyTransaction;
use App\Models\CustomerWalletTransaction;
use App\Support\Money;
use DomainException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Lunar\DataTypes\Price;
use Lunar\Models\Customer as LunarCustomer;
use Lunar\Models\Order;

class CustomerLoyaltyService
{
    /**
     * Conversion rate: 1 point earned per 1,000 minor units (10 EGP) spent.
     */
    public const MINOR_UNITS_PER_POINT = 1000;

    /**
     * Redemption rate: 1 point = 10 minor units (0.10 EGP discount / wallet credit).
     * e.g., 100 points = 1,000 minor units = 10 EGP.
     */
    public const REDEMPTION_MINOR_PER_POINT = 10;

    public function __construct(
        protected CustomerWalletServiceInterface $walletService,
        protected Ledger $ledger,
        protected AccountResolver $accountResolver
    ) {}

    /**
     * Resolve or create the customer's loyalty account.
     */
    public function getAccount(AppCustomer|LunarCustomer|int $customer): CustomerLoyaltyAccount
    {
        $customerId = is_int($customer) ? $customer : (int) $customer->id;
        $tenantId = tenant('id') ? (string) tenant('id') : 'default';

        /** @var CustomerLoyaltyAccount $account */
        $account = CustomerLoyaltyAccount::firstOrCreate(
            [
                'tenant_id' => $tenantId,
                'customer_id' => $customerId,
            ],
            [
                'points_balance' => 0,
                'total_earned_points' => 0,
                'total_redeemed_points' => 0,
            ]
        );

        return $account;
    }

    /**
     * Get the customer's current points balance.
     */
    public function getPointsBalance(AppCustomer|LunarCustomer|int $customer): int
    {
        return $this->getAccount($customer)->points_balance;
    }

    /**
     * Calculate points earned for a given order total minor amount.
     */
    public function calculatePointsForAmount(int $amountMinor): int
    {
        if ($amountMinor <= 0) {
            return 0;
        }

        return (int) floor($amountMinor / self::MINOR_UNITS_PER_POINT);
    }

    /**
     * Convert points to minor money value (e.g., 100 points = 1,000 minor units).
     */
    public function calculateDiscountFromPoints(int $points): int
    {
        if ($points <= 0) {
            return 0;
        }

        return $points * self::REDEMPTION_MINOR_PER_POINT;
    }

    /**
     * Award loyalty points for a delivered order with ledger synchronization.
     */
    public function awardPointsForOrder(Order $order): ?CustomerLoyaltyTransaction
    {
        /** @var int|null $storefrontCustomerId */
        $storefrontCustomerId = $order->getAttribute('storefront_customer_id');
        $customerId = $order->customer_id ?? $storefrontCustomerId;
        if (! $customerId) {
            return null;
        }

        $tenantId = $order->tenant_id ?? (tenant('id') ? (string) tenant('id') : 'default');

        // Check idempotency: avoid awarding points multiple times for the same order
        $alreadyAwarded = CustomerLoyaltyTransaction::query()
            ->where('tenant_id', $tenantId)
            ->where('order_id', $order->id)
            ->where('type', 'earn_order')
            ->exists();

        if ($alreadyAwarded) {
            return null;
        }

        /** @var mixed $rawTotal */
        $rawTotal = $order->total;
        $orderTotalMinor = (int) ($rawTotal instanceof Price ? $rawTotal->value : $rawTotal);

        $points = $this->calculatePointsForAmount($orderTotalMinor);
        if ($points <= 0) {
            return null;
        }

        return DB::transaction(function () use ($order, $customerId, $points): CustomerLoyaltyTransaction {
            $account = $this->getAccount($customerId);
            $transaction = $account->award(
                points: $points,
                type: 'earn_order',
                orderId: (int) $order->id,
                notes: __('نقاط ولاء مكتسبة عن الطلب #:reference', ['reference' => $order->reference])
            );

            // Post financial ledger entry
            $discountMinor = $this->calculateDiscountFromPoints($points);
            if ($discountMinor > 0) {
                $loyaltyAccount = $this->accountResolver->getCustomerLoyaltyAccount($customerId);
                $expenseAccount = $this->accountResolver->getStoreLoyaltyExpenseAccount();

                $entryData = new EntryData(
                    type: 'loyalty_points_earned',
                    idempotencyKey: "loyalty_points_order_{$order->id}",
                    description: __('استحقاق نقاط ولاء العميل للطلب #:reference (:points نقطة)', ['reference' => $order->reference, 'points' => $points]),
                    lines: [
                        new LedgerLineData(account: $loyaltyAccount, amountMinor: $discountMinor),
                        new LedgerLineData(account: $expenseAccount, amountMinor: -$discountMinor),
                    ],
                    refType: Order::class,
                    refId: (int) $order->id,
                    createdBy: null
                );

                $this->ledger->post($entryData);
            }

            return $transaction;
        });
    }

    /**
     * Atomically convert loyalty points to wallet balance credit.
     *
     * @return array{transaction: CustomerLoyaltyTransaction, wallet_transaction: CustomerWalletTransaction}
     */
    public function redeemPointsToWallet(AppCustomer|LunarCustomer|int $customer, int $points, ?string $notes = null): array
    {
        if ($points <= 0) {
            throw new InvalidArgumentException(__('عدد النقاط المستبدلة يجب أن يكون أكبر من الصفر.'));
        }

        $account = $this->getAccount($customer);
        if ($account->points_balance < $points) {
            throw new DomainException(sprintf(
                __('رصيد النقاط غير كافٍ. المتاح: %d، المطلوب: %d'),
                $account->points_balance,
                $points
            ));
        }

        $amountMinor = $this->calculateDiscountFromPoints($points);

        return DB::transaction(function () use ($customer, $account, $points, $amountMinor, $notes): array {
            $transaction = $account->redeem(
                points: $points,
                type: 'redeem_to_wallet',
                orderId: null,
                notes: $notes ?? __('تحويل :points نقطة ولاء إلى رصيد بالمحفظة', ['points' => $points])
            );

            $walletTx = $this->walletService->credit(
                customer: $customer,
                amountMinor: $amountMinor,
                type: CustomerWalletTransaction::TYPE_REWARD,
                referenceId: "loyalty_tx_{$transaction->id}",
                notes: $notes ?? __('رصيد ناتج عن استبدال :points نقطة ولاء', ['points' => $points])
            );

            $customerId = is_int($customer) ? $customer : (int) $customer->id;
            /** @var LunarCustomer|null $lunarCustomer */
            $lunarCustomer = LunarCustomer::find($customerId);
            if ($lunarCustomer instanceof LunarCustomer) {
                $loyaltyAccount = $this->accountResolver->getCustomerLoyaltyAccount($lunarCustomer);
                $walletAccount = $this->accountResolver->getCustomerWalletAccount($lunarCustomer);

                $entryData = new EntryData(
                    type: 'loyalty_redeemed_to_wallet',
                    idempotencyKey: "loyalty_redeem_{$transaction->id}",
                    description: __('تحويل نقاط ولاء إلى رصيد محفظة (:points نقطة = ', ['points' => $points]).number_format($amountMinor / 100, 2).' '.Money::symbol().')',
                    lines: [
                        new LedgerLineData(account: $walletAccount, amountMinor: $amountMinor),
                        new LedgerLineData(account: $loyaltyAccount, amountMinor: -$amountMinor),
                    ],
                    refType: CustomerLoyaltyTransaction::class,
                    refId: (int) $transaction->id,
                    createdBy: null
                );

                $this->ledger->post($entryData);
            }

            return [
                'transaction' => $transaction,
                'wallet_transaction' => $walletTx,
            ];
        });
    }
}
