<?php

declare(strict_types=1);

namespace App\Domain\Promotions\Services;

use App\Domain\Promotions\Events\LoyaltyPointsEarned;
use App\Domain\Promotions\Events\LoyaltyPointsRedeemed;
use App\Models\Customer;
use App\Models\CustomerLoyaltyAccount;
use App\Models\CustomerLoyaltyTransaction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;
use Lunar\Models\Order;

class LoyaltyPointsService
{
    /**
     * Ratio: 1 point per 1000 minor units (10 EGP) spent.
     */
    public const EARN_MINOR_PER_POINT = 1000;

    /**
     * Value: 100 points = 1000 minor units (10 EGP) discount.
     * Therefore, 1 point = 10 minor units (0.10 EGP).
     */
    public const REDEEM_MINOR_PER_POINT = 10;

    /**
     * Get or initialize customer loyalty account.
     */
    public function getAccount(Customer|Model|int $customer): CustomerLoyaltyAccount
    {
        $customerId = is_int($customer) ? $customer : (int) $customer->getKey();

        /** @var CustomerLoyaltyAccount $account */
        $account = CustomerLoyaltyAccount::firstOrCreate(
            ['customer_id' => $customerId],
            [
                'points_balance' => 0,
                'total_earned_points' => 0,
                'total_redeemed_points' => 0,
            ]
        );

        return $account;
    }

    /**
     * Alias for getAccount.
     */
    public function getOrCreateAccount(Customer|Model|int $customer): CustomerLoyaltyAccount
    {
        return $this->getAccount($customer);
    }

    /**
     * Award loyalty points for a successfully delivered order.
     * Strictly idempotent: an order is rewarded at most once.
     */
    public function awardForDeliveredOrder(Order $order): ?CustomerLoyaltyTransaction
    {
        $customerId = $order->storefront_customer_id ?? ($order->customer_id ?? null);
        if (! $customerId && ! empty($order->customer_phone_e164)) {
            $matchedCustomer = Customer::where('phone_e164', $order->customer_phone_e164)->first();
            $customerId = $matchedCustomer?->id;
        }

        if (! $customerId) {
            return null;
        }

        // Idempotency: verify no prior earning transaction exists for this order
        $alreadyAwarded = CustomerLoyaltyTransaction::query()
            ->where('order_id', $order->id)
            ->where('type', CustomerLoyaltyTransaction::TYPE_EARN_ORDER)
            ->exists();

        if ($alreadyAwarded) {
            return null;
        }

        /** @var mixed $orderTotal */
        $orderTotal = $order->getAttribute('total');
        $totalMinor = is_object($orderTotal) && isset($orderTotal->value) ? (int) $orderTotal->value : (int) ($orderTotal ?? 0);

        $pointsToAward = (int) floor($totalMinor / self::EARN_MINOR_PER_POINT);
        if ($pointsToAward <= 0) {
            return null;
        }

        $account = $this->getAccount((int) $customerId);
        $transaction = $account->award(
            points: $pointsToAward,
            type: CustomerLoyaltyTransaction::TYPE_EARN_ORDER,
            orderId: $order->id,
            notes: "مكافأة نقاط ولاء عن تسليم الطلب رقم {$order->reference}"
        );

        Event::dispatch(new LoyaltyPointsEarned($account, $transaction, $order));

        return $transaction;
    }

    /**
     * Redeem customer loyalty points as order discount.
     */
    public function redeem(Customer|Model|int $customer, int $points, ?Order $order = null): CustomerLoyaltyTransaction
    {
        $account = $this->getAccount($customer);
        $notes = $order ? "استبدال نقاط ولاء في الطلب {$order->reference}" : 'استبدال نقاط ولاء كخصم مشتريات';

        $transaction = $account->redeem(
            points: $points,
            type: CustomerLoyaltyTransaction::TYPE_REDEEM_ORDER,
            orderId: $order?->id,
            notes: $notes
        );

        Event::dispatch(new LoyaltyPointsRedeemed($account, $transaction, $order));

        return $transaction;
    }

    /**
     * Convert points to cash discount in minor units.
     */
    public function pointsToDiscountMinor(int $points): int
    {
        return max(0, $points * self::REDEEM_MINOR_PER_POINT);
    }

    /**
     * Award arbitrary points (bonus, manual adjustment, etc.)
     */
    public function award(Customer|Model|int $customer, int $points, string $type = 'bonus', ?string $notes = null): CustomerLoyaltyTransaction
    {
        $account = $this->getAccount($customer);
        $transaction = $account->award(
            points: $points,
            type: $type,
            notes: $notes
        );

        Event::dispatch(new LoyaltyPointsEarned($account, $transaction));

        return $transaction;
    }

    /**
     * Alias for award.
     */
    public function awardPoints(Customer|Model|int $customer, int $points, string $source = 'bonus', ?string $description = null): CustomerLoyaltyTransaction
    {
        return $this->award($customer, $points, $source, $description);
    }

    /**
     * Redeem points and return the discount value in minor units.
     */
    public function redeemPoints(Customer|Model|int $customer, int $points, ?string $description = null): int
    {
        $this->redeem($customer, $points);

        return $this->pointsToDiscountMinor($points);
    }
}
