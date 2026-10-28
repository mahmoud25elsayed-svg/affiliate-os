<?php

declare(strict_types=1);

namespace App\Domain\Affiliate\Actions;

use App\Domain\Orders\DTOs\OrderActor;
use App\Domain\Orders\Enums\OrderStatus;
use App\Domain\Orders\Services\OrderTransitionService;
use App\Models\Affiliate;
use App\Support\Enums\ReasonCode;
use DomainException;
use Lunar\Models\Order;

class CancelAffiliateOrderAction
{
    public function __construct(
        protected OrderTransitionService $orderTransitionService
    ) {}

    /**
     * @throws DomainException
     */
    public function execute(Affiliate $affiliate, Order $order, ?string $reason = null): Order
    {
        if ((int) $order->getAttribute('affiliate_id') !== $affiliate->id) {
            throw new DomainException('هذا الطلب غير مرتبط بحسابك ولا يمكنك إلغاؤه.');
        }

        // Check if order is already packed or in a subsequent delivery state
        $disallowedStatuses = [
            OrderStatus::PACKED->value,
            OrderStatus::SHIPPED->value,
            OrderStatus::OUT_FOR_DELIVERY->value,
            OrderStatus::DELIVERED->value,
            OrderStatus::DELIVERY_FAILED->value,
            OrderStatus::RETURNING->value,
            OrderStatus::RETURNED->value,
            OrderStatus::SETTLED->value,
            OrderStatus::CANCELLED_BY_CUSTOMER->value,
            OrderStatus::CANCELLED_NO_ANSWER->value,
        ];

        if (in_array($order->status, $disallowedStatuses, true)) {
            throw new DomainException('لا يمكن إلغاء الطلب بعد تجهيزه وتعبئته في المخزن أو تسليمه لشركة الشحن.');
        }

        $actor = OrderActor::affiliate($affiliate->id, $affiliate->name);
        $note = ! empty($reason) ? "تم الإلغاء بواسطة المسوق: {$reason}" : 'تم إلغاء الطلب من لوحة تحكم المسوق قبل التجهيز.';

        return $this->orderTransitionService->transition(
            order: $order,
            to: OrderStatus::CANCELLED_BY_CUSTOMER,
            reason: ReasonCode::CUSTOMER_CHANGED_MIND,
            actor: $actor,
            note: $note
        );
    }
}
