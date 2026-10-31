<?php

declare(strict_types=1);

namespace App\Domain\Affiliate\Actions;

use App\Domain\Affiliate\DTOs\PriceQuoteResult;
use App\Domain\Affiliate\Settings\AffiliateProgramSettings;
use App\Domain\Orders\DTOs\OrderActor;
use App\Domain\Orders\Enums\OrderStatus;
use App\Domain\Orders\Events\OrderSubmitted;
use App\Domain\Orders\QualityGate\DTOs\QualityGateResult;
use App\Domain\Orders\QualityGate\QualityGateService;
use App\Models\Affiliate;
use App\Models\OrderEvent;
use App\Support\Money;
use App\Support\Phone\PhoneNumber;
use DomainException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Lunar\Base\ValueObjects\Cart\TaxBreakdown;
use Lunar\Models\Channel;
use Lunar\Models\Order;
use Lunar\Models\OrderAddress;
use Lunar\Models\OrderLine;
use Lunar\Models\ProductVariant;

class SubmitAffiliateOrderAction
{
    public function __construct(
        protected PriceQuoteAction $priceQuoteAction,
        protected QualityGateService $qualityGateService
    ) {}

    /**
     * @param  array{
     *     customer_name: string,
     *     customer_phone: string,
     *     customer_alt_phone?: string|null,
     *     governorate_id: int,
     *     city: string,
     *     address: string,
     *     building_number?: string|null,
     *     floor?: string|null,
     *     flat?: string|null,
     *     notes?: string|null,
     *     items: list<array{variant_id: int, quantity: int, selling_price_minor: int}>,
     *     idempotency_key?: string|null,
     *     ip_address?: string|null
     * }  $payload
     *
     * @throws DomainException
     */
    public function execute(Affiliate $affiliate, array $payload): Order
    {
        // 1. Verify Affiliate Account Status
        if (! $affiliate->isActive()) {
            throw new DomainException(__('حساب المسوق غير نشط حالياً ولا يمكنك إنشاء طلبات جديدة.'));
        }

        // 2. Check Daily Order Quota Limit
        $programSettings = AffiliateProgramSettings::load();
        $dailyLimit = $programSettings->getDailyOrderLimit();

        $todayOrdersCount = Order::query()
            ->where('tenant_id', $affiliate->tenant_id)
            ->where('affiliate_id', $affiliate->id)
            ->where('created_at', '>=', Carbon::today())
            ->count();

        if ($todayOrdersCount >= $dailyLimit) {
            throw new DomainException(__('عذراً، لقد استنفدت الحد الأقصى للطلبات اليومية المسموح به لحسابك (:daily_limit طلب يومياً).', ['daily_limit' => $dailyLimit]));
        }

        // 3. Idempotency Check
        $idempotencyKey = ! empty($payload['idempotency_key']) ? trim($payload['idempotency_key']) : null;
        if ($idempotencyKey !== null) {
            /** @var Order|null $existingOrder */
            $existingOrder = Order::query()
                ->where('tenant_id', $affiliate->tenant_id)
                ->where('affiliate_id', $affiliate->id)
                ->where('idempotency_key', $idempotencyKey)
                ->first();

            if ($existingOrder instanceof Order) {
                return $existingOrder;
            }
        }

        // 4. Validate & Normalize Phone
        $phone = PhoneNumber::tryParse($payload['customer_phone']);
        if ($phone === null) {
            throw new DomainException(PhoneNumber::invalidMessage());
        }

        if (empty($payload['items'])) {
            throw new DomainException(__('يجب إضافة منتج واحد على الأقل في الطلب.'));
        }

        // 5. Calculate Server-side Quotes for all items
        $governorateId = (int) $payload['governorate_id'];
        $quotes = [];
        $totalSubtotalMinor = 0;
        $totalAffiliateProfitMinor = 0;
        $variantIds = [];

        foreach ($payload['items'] as $itemData) {
            /** @var ProductVariant|null $variant */
            $variant = ProductVariant::with(['wholesalePrice', 'product'])->find($itemData['variant_id']);
            if (! $variant instanceof ProductVariant) {
                throw new DomainException(__('المنتج المحدد (ID: :variant_id) غير موجود.', ['variant_id' => $itemData['variant_id']]));
            }

            $quote = $this->priceQuoteAction->execute(
                variant: $variant,
                quantity: (int) $itemData['quantity'],
                sellingPriceMinor: (int) $itemData['selling_price_minor'],
                governorateId: $governorateId
            );

            $quotes[] = [
                'variant' => $variant,
                'quote' => $quote,
            ];

            $totalSubtotalMinor += $quote->subtotalMinor;
            $totalAffiliateProfitMinor += $quote->affiliateProfitMinor;
            $variantIds[] = $variant->id;
        }

        // Use shipping and COD fee from the first item quote (governorate based)
        /** @var PriceQuoteResult $firstQuote */
        $firstQuote = $quotes[0]['quote'];
        $shippingCostMinor = $firstQuote->shippingCostMinor;
        $codFeeMinor = $firstQuote->codFeeMinor;
        $totalCustomerMinor = $totalSubtotalMinor + $shippingCostMinor + $codFeeMinor;

        // 6. Run Quality Gate v1
        $qualityResult = $this->qualityGateService->evaluate(
            customerName: trim($payload['customer_name']),
            customerPhone: $phone->e164,
            governorateId: $governorateId,
            city: trim($payload['city']),
            address: trim($payload['address']),
            variantIds: $variantIds,
            ipAddress: $payload['ip_address'] ?? null
        );

        // 7. Initial Order Status Based on Quality Gate
        $initialStatus = match ($qualityResult->status) {
            QualityGateResult::STATUS_REJECT => OrderStatus::REJECTED_QUALITY->value,
            QualityGateResult::STATUS_REVIEW => OrderStatus::PENDING_CONFIRMATION->value,
            default => OrderStatus::SUBMITTED->value,
        };

        // 8. Create Order & Lines in DB Transaction
        return DB::transaction(function () use (
            $affiliate,
            $payload,
            $phone,
            $governorateId,
            $quotes,
            $totalSubtotalMinor,
            $shippingCostMinor,
            $codFeeMinor,
            $totalAffiliateProfitMinor,
            $totalCustomerMinor,
            $qualityResult,
            $initialStatus,
            $idempotencyKey
        ): Order {
            $publicRef = 'AFF-'.date('y').'-'.strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));

            $channel = Channel::first();
            $channelId = $channel instanceof Channel ? $channel->id : 1;

            $priceSnapshot = [
                'type' => 'affiliate_direct',
                'subtotal_minor' => $totalSubtotalMinor,
                'shipping_minor' => $shippingCostMinor,
                'cod_fee_minor' => $codFeeMinor,
                'total_minor' => $totalCustomerMinor,
                'affiliate_profit_minor' => $totalAffiliateProfitMinor,
                'currency' => Money::defaultCurrency(),
                'quality_result' => $qualityResult->toArray(),
                'items' => array_map(fn ($q) => $q['quote']->toArray(), $quotes),
            ];

            /** @var Order $order */
            $order = Order::create([
                'tenant_id' => $affiliate->tenant_id,
                'affiliate_id' => $affiliate->id,
                'affiliate_profit_minor' => $totalAffiliateProfitMinor,
                'channel_id' => $channelId,
                'status' => $initialStatus,
                'reference' => $publicRef,
                'customer_reference' => $publicRef,
                'sub_total' => $totalSubtotalMinor,
                'discount_total' => 0,
                'shipping_total' => $shippingCostMinor,
                'tax_breakdown' => new TaxBreakdown,
                'tax_total' => 0,
                'total' => $totalCustomerMinor,
                'currency_code' => Money::defaultCurrency(),
                'placed_at' => now(),
                'customer_phone_e164' => $phone->e164,
                'customer_phone_hash' => $phone->hash(),
                'governorate_id' => $governorateId,
                'cod_fee_minor' => $codFeeMinor,
                'payment_method' => 'cod',
                'idempotency_key' => $idempotencyKey,
                'price_snapshot' => json_encode($priceSnapshot),
                'quality_result' => $qualityResult->status,
                'quality_flags' => json_encode($qualityResult->flags),
                'notes' => $payload['notes'] ?? null,
                'meta' => [
                    'source' => 'affiliate_direct_portal',
                    'affiliate_code' => $affiliate->code,
                    'city' => $payload['city'],
                    'address' => $payload['address'],
                    'building' => $payload['building_number'] ?? null,
                    'floor' => $payload['floor'] ?? null,
                    'flat' => $payload['flat'] ?? null,
                    'alt_phone' => $payload['customer_alt_phone'] ?? null,
                    'quality_reasons' => $qualityResult->reasons,
                ],
            ]);

            // Create Order Lines
            foreach ($quotes as $item) {
                /** @var ProductVariant $variant */
                $variant = $item['variant'];
                /** @var PriceQuoteResult $quote */
                $quote = $item['quote'];

                OrderLine::create([
                    'tenant_id' => $affiliate->tenant_id,
                    'order_id' => $order->id,
                    'purchasable_type' => ProductVariant::class,
                    'purchasable_id' => $variant->id,
                    'type' => 'physical',
                    'description' => $quote->productName,
                    'identifier' => $quote->sku,
                    'unit_price' => $quote->sellingUnitPriceMinor,
                    'unit_quantity' => 1,
                    'quantity' => $quote->quantity,
                    'sub_total' => $quote->subtotalMinor,
                    'discount_total' => 0,
                    'tax_breakdown' => new TaxBreakdown,
                    'tax_total' => 0,
                    'total' => $quote->subtotalMinor,
                    'meta' => [
                        'wholesale_unit_price_minor' => $quote->wholesaleUnitPriceMinor,
                        'selling_unit_price_minor' => $quote->sellingUnitPriceMinor,
                        'affiliate_profit_minor' => $quote->affiliateProfitMinor,
                        'warnings' => $quote->warnings,
                    ],
                ]);
            }

            // Create Order Shipping Address
            OrderAddress::create([
                'order_id' => $order->id,
                'first_name' => trim($payload['customer_name']),
                'last_name' => '',
                'line_one' => trim($payload['address']),
                'line_two' => trim(($payload['building_number'] ?? '').' '.($payload['floor'] ?? '').' '.($payload['flat'] ?? '')),
                'city' => trim($payload['city']),
                'contact_phone' => $phone->national,
                'delivery_instructions' => $payload['notes'] ?? null,
                'type' => 'shipping',
                'shipping_option' => 'standard',
            ]);

            // Audit Event
            $orderEvent = OrderEvent::create([
                'tenant_id' => $affiliate->tenant_id,
                'order_id' => $order->id,
                'event_type' => 'affiliate_order_created',
                'from_status' => null,
                'to_status' => $initialStatus,
                'actor_type' => 'affiliate',
                'actor_id' => (string) $affiliate->id,
                'actor_name' => $affiliate->name,
                'metadata' => [
                    'quality_result' => $qualityResult->status,
                    'quality_flags' => $qualityResult->flags,
                    'affiliate_profit_minor' => $totalAffiliateProfitMinor,
                ],
            ]);

            // Dispatch OrderSubmitted Event
            Event::dispatch(new OrderSubmitted($order, $orderEvent, null, OrderActor::affiliate($affiliate->id, $affiliate->name)));

            return $order;
        });
    }
}
