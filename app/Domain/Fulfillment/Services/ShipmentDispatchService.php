<?php

declare(strict_types=1);

namespace App\Domain\Fulfillment\Services;

use App\Domain\Fulfillment\DTOs\ShipmentCreationData;
use App\Domain\Fulfillment\Enums\ShipmentStatus;
use App\Domain\Orders\DTOs\OrderActor;
use App\Domain\Orders\Enums\OrderStatus;
use App\Domain\Orders\Services\OrderTransitionService;
use App\Models\CourierAccount;
use App\Models\Shipment;
use App\Models\User;
use DomainException;
use Illuminate\Support\Carbon;
use Lunar\DataTypes\Price;
use Lunar\Models\Order;
use Lunar\Models\OrderAddress;

class ShipmentDispatchService
{
    public function __construct(
        protected CourierSelectionService $selectionService,
        protected CourierManager $courierManager,
        protected OrderTransitionService $transitionService
    ) {}

    /**
     * Dispatch an order to a courier, generate the shipment and waybill, and transition order state.
     */
    public function dispatchOrder(Order $order, ?CourierAccount $courierAccount = null, ?User $staff = null): Shipment
    {
        // 1. Resolve Courier Account
        $account = $courierAccount ?? $this->selectionService->selectCourier($order);
        if (! $account instanceof CourierAccount) {
            throw new DomainException(__('لم يتم العثور على حساب شركة شحن نشط لإسناد هذا الطلب.'));
        }

        // 2. Resolve Shipping Address & Recipient
        /** @var OrderAddress|null $shippingAddress */
        $shippingAddress = $order->shippingAddress()->first();

        $recipientName = __('العميل');
        $recipientPhone = '01000000000';
        $deliveryCity = __('القاهرة');
        $deliveryAddress = __('عنوان التسليم');

        if ($shippingAddress instanceof OrderAddress) {
            $fullName = trim((string) ($shippingAddress->first_name ?? '').' '.(string) ($shippingAddress->last_name ?? ''));
            if ($fullName !== '') {
                $recipientName = $fullName;
            }
            if (! empty($shippingAddress->contact_phone)) {
                $recipientPhone = (string) $shippingAddress->contact_phone;
            }
            if (! empty($shippingAddress->city)) {
                $deliveryCity = (string) $shippingAddress->city;
            }
            if (! empty($shippingAddress->line_one)) {
                $deliveryAddress = (string) $shippingAddress->line_one;
            }
        }

        /** @var mixed $rawTotal */
        $rawTotal = $order->total;
        $codAmountMinor = (int) ($rawTotal instanceof Price ? $rawTotal->value : $rawTotal);

        $itemsCount = max(1, (int) $order->lines()->sum('quantity'));

        // 3. Prepare creation DTO
        $creationData = new ShipmentCreationData(
            order: $order,
            recipientName: $recipientName,
            recipientPhone: $recipientPhone,
            deliveryGovernorate: $deliveryCity,
            deliveryCity: $deliveryCity,
            deliveryAddress: $deliveryAddress,
            codAmountMinor: $codAmountMinor,
            declaredValueMinor: $codAmountMinor,
            itemsCount: $itemsCount,
            description: __('طلب #:reference', ['reference' => $order->reference]),
            notes: (string) ($order->notes ?? __('يرجى الاتصال قبل التسليم'))
        );

        // 4. Create Shipment via driver
        $driver = $this->courierManager->driver($account);
        $result = $driver->createShipment($creationData);

        if (! $result->success) {
            throw new DomainException(__('فشل إصدار الشحنة من شركة الشحن: :error_message', ['error_message' => $result->errorMessage]));
        }

        $now = Carbon::now();

        // 5. Store Shipment Model
        /** @var Shipment $shipment */
        $shipment = Shipment::create([
            'tenant_id' => $order->tenant_id ?? (tenant('id') ? (string) tenant('id') : 'default'),
            'order_id' => $order->id,
            'courier_account_id' => $account->id,
            'tracking_number' => $result->trackingNumber,
            'waybill_url' => $result->waybillUrl,
            'status' => ShipmentStatus::CREATED,
            'cod_amount_minor' => $codAmountMinor,
            'shipping_fees_minor' => $result->shippingFeeMinor,
            'recipient_name' => $recipientName,
            'recipient_phone' => $recipientPhone,
            'delivery_city' => $deliveryCity,
            'delivery_address' => $deliveryAddress,
            'label_data' => $result->rawResponse,
            'status_history' => [
                [
                    'status' => ShipmentStatus::CREATED->value,
                    'timestamp' => $now->toIso8601String(),
                    'note' => __('تم تسليم بيانات الشحنة لشركة :name', ['name' => $account->name]),
                ],
            ],
            'dispatched_at' => $now,
        ]);

        // 6. Transition Order Status
        $actor = $staff instanceof User
            ? OrderActor::staff($staff, role: 'orders')
            : OrderActor::system();

        $currentStatus = OrderStatus::tryFrom((string) $order->status);

        // Progressively transition through required state machine steps
        if ($currentStatus === OrderStatus::CONFIRMED) {
            $this->transitionService->transition(order: $order, to: OrderStatus::RESERVED, reason: null, actor: $actor);
            $currentStatus = OrderStatus::RESERVED;
        }

        if ($currentStatus === OrderStatus::RESERVED) {
            $this->transitionService->transition(order: $order, to: OrderStatus::PACKED, reason: null, actor: $actor);
            $currentStatus = OrderStatus::PACKED;
        }

        if ($currentStatus === OrderStatus::PACKED) {
            $this->transitionService->transition(
                order: $order,
                to: OrderStatus::SHIPPED,
                reason: null,
                actor: $actor,
                meta: [
                    'tracking_number' => $result->trackingNumber,
                    'courier' => $account->name,
                    'waybill_url' => $result->waybillUrl,
                ],
                note: __('تم تسليم الشحنة لشركة :name برقم تتبع :tracking_number.', ['name' => $account->name, 'tracking_number' => $result->trackingNumber])
            );
        }

        return $shipment;
    }
}
