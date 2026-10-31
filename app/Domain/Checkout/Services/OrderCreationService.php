<?php

declare(strict_types=1);

namespace App\Domain\Checkout\Services;

use App\Domain\Affiliate\Services\AffiliateTrackingService;
use App\Domain\Checkout\DTOs\CheckoutPayload;
use App\Domain\Orders\DTOs\OrderActor;
use App\Domain\Orders\Enums\OrderStatus;
use App\Domain\Orders\Events\OrderSubmitted;
use App\Domain\Orders\Services\OrderTransitionService;
use App\Domain\Promotions\Services\PromotionPricingService;
use App\Models\Coupon;
use App\Models\Customer;
use App\Models\OrderEvent;
use App\Models\Tenant;
use App\Support\Billing\Limits;
use App\Support\Enums\ReasonCode;
use App\Support\Idempotency\IdempotencyService;
use App\Support\Money;
use App\Support\Phone\PhoneNumber;
use App\Support\Settings\ShippingSettings;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Lunar\Base\ValueObjects\Cart\TaxBreakdown;
use Lunar\Models\Channel;
use Lunar\Models\Order;
use Lunar\Models\OrderAddress;
use Lunar\Models\OrderLine;
use Lunar\Models\Product;
use Lunar\Models\ProductVariant;

class OrderCreationService
{
    public function __construct(
        protected ShippingCalculatorService $shippingCalculator,
        protected CouponService $couponService,
        protected SpamProtectionService $spamProtection,
        protected PaymentManager $paymentManager,
        protected OrderTransitionService $orderTransitionService
    ) {}

    public function create(CheckoutPayload $payload): Order
    {
        if ($payload->idempotency_token !== null && trim($payload->idempotency_token) !== '') {
            $key = 'order_create_'.trim($payload->idempotency_token);

            /** @var Order $order */
            $order = IdempotencyService::run($key, fn () => $this->executeOrderCreation($payload));

            return $order;
        }

        return $this->executeOrderCreation($payload);
    }

    protected function executeOrderCreation(CheckoutPayload $payload): Order
    {
        return DB::transaction(function () use ($payload) {
            // 1. Phone validation & normalization
            $phone = PhoneNumber::tryParse($payload->customer_phone);
            if ($phone === null) {
                throw ValidationException::withMessages([
                    'customer_phone' => [PhoneNumber::invalidMessage()],
                ]);
            }

            // 1.1 Affiliate Attribution Resolution & Self-Referral Check (AF-10)
            $affiliateTracking = app(AffiliateTrackingService::class);
            $rawToken = $payload->affiliate_ref_token ?? request()->cookie(AffiliateTrackingService::COOKIE_NAME);
            $tokenStr = is_string($rawToken) ? $rawToken : null;
            $attributedLink = $affiliateTracking->resolveAttributedLink($tokenStr);

            /** @var Customer|null $customerUser */
            $customerUser = Auth::guard('customer')->user();
            $customerEmail = $customerUser?->email;

            $attributedAffiliateId = null;
            $attributedLinkId = null;
            $selfReferralBlocked = false;

            if ($attributedLink !== null && $attributedLink->affiliate !== null) {
                if ($affiliateTracking->isSelfReferral($attributedLink->affiliate, $phone->e164, $customerEmail)) {
                    $selfReferralBlocked = true;
                    $attributedLink = null;
                } else {
                    $attributedAffiliateId = $attributedLink->affiliate_id;
                    $attributedLinkId = $attributedLink->id;
                }
            }

            // 2. Validate Items
            if (empty($payload->items)) {
                throw ValidationException::withMessages([
                    'items' => ['سلة التسوق فارغة.'],
                ]);
            }

            $lineItemsData = [];
            $itemIds = [];
            $categoryIds = [];
            $subtotalMinor = 0;

            foreach ($payload->items as $item) {
                $purchasableId = (int) $item['purchasable_id'];
                $quantity = max(1, (int) $item['quantity']);

                /** @var ProductVariant|null $variant */
                $variant = ProductVariant::query()
                    ->with(['product.collections', 'prices'])
                    ->find($purchasableId);

                if ($variant === null) {
                    throw ValidationException::withMessages([
                        'items' => ["المنتج المحدد غير متوفر (معرف: {$purchasableId})."],
                    ]);
                }

                $linkedPrice = $attributedLink !== null ? $affiliateTracking->linkedPriceMinor($variant, $attributedLink) : null;
                $unitPriceMinor = $linkedPrice
                    ?? app(PromotionPricingService::class)->getEffectiveVariantPrice($variant)['price_minor'];
                $lineSubtotalMinor = $unitPriceMinor * $quantity;
                $subtotalMinor += $lineSubtotalMinor;

                $itemIds[] = $variant->id;

                /** @var Product|null $product */
                $product = $variant->product;

                if ($product instanceof Product) {
                    foreach ($product->collections as $collection) {
                        $categoryIds[] = (int) $collection->getKey();
                    }
                }

                $productName = $product instanceof Product ? ($product->translateAttribute('name') ?? 'منتج') : 'منتج';
                $sku = $variant->sku ?? 'SKU-'.$variant->id;

                $lineItemsData[] = [
                    'variant' => $variant,
                    'product_name' => $productName,
                    'sku' => $sku,
                    'quantity' => $quantity,
                    'unit_price_minor' => $unitPriceMinor,
                    'subtotal_minor' => $lineSubtotalMinor,
                ];
            }

            // 3. Coupon Calculation
            $coupon = null;
            $discountMinor = 0;
            if ($payload->coupon_code !== null && trim($payload->coupon_code) !== '') {
                $couponResult = $this->couponService->validateAndCalculate(
                    $payload->coupon_code,
                    $subtotalMinor,
                    $itemIds,
                    array_values(array_unique($categoryIds)),
                    $phone->e164
                );

                if (! $couponResult->isValid) {
                    throw ValidationException::withMessages([
                        'coupon_code' => [$couponResult->errorMessage ?? 'الكوبون غير صالح.'],
                    ]);
                }

                $coupon = $couponResult->coupon;
                $discountMinor = $couponResult->discountMinor;
            }

            // 4. Shipping Calculation (Strictly server-side)
            $shippingQuote = $this->shippingCalculator->calculate(
                $payload->governorate_id,
                $subtotalMinor,
                $coupon
            );
            $shippingMinor = $shippingQuote->rate_minor;

            // 5. COD Fee Calculation
            $settings = ShippingSettings::load();
            $codFeeMinor = 0;
            if ($payload->payment_method === 'cod' && $settings->allow_cod) {
                $codFeeMinor = $settings->cod_fee_minor;
            }

            // 6. Total Calculation (piasters)
            $discountedSubtotal = max(0, $subtotalMinor - $discountMinor);
            $totalMinor = $discountedSubtotal + $shippingMinor + $codFeeMinor;

            // 7. Spam & Duplicate Check
            $spamResult = $this->spamProtection->check($phone->e164, $itemIds);

            // 8. Public Order Reference
            $publicRef = 'ORD-'.date('y').'-'.strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));

            $priceSnapshot = [
                'subtotal_minor' => $subtotalMinor,
                'discount_minor' => $discountMinor,
                'shipping_minor' => $shippingMinor,
                'cod_fee_minor' => $codFeeMinor,
                'total_minor' => $totalMinor,
                'currency' => Money::defaultCurrency(),
                'coupon' => $coupon ? [
                    'code' => $coupon->code,
                    'type' => $coupon->type,
                    'value_minor' => $coupon->value_minor,
                ] : null,
                'shipping_details' => $shippingQuote->toArray(),
                'items' => array_map(fn ($item) => [
                    'purchasable_id' => $item['variant']->id,
                    'sku' => $item['sku'],
                    'name' => $item['product_name'],
                    'unit_price_minor' => $item['unit_price_minor'],
                    'quantity' => $item['quantity'],
                    'subtotal_minor' => $item['subtotal_minor'],
                ], $lineItemsData),
            ];

            $channel = Channel::first();
            $channelId = $channel instanceof Channel ? $channel->id : 1;

            // 8.1 Affiliate Commission Calculation (AF-09, AF-10)
            $affiliateCommissionMinor = 0;
            // A blocked self-referral has already cleared $attributedLink above.
            if ($attributedLink !== null) {
                $effectiveSubtotal = max(0, $subtotalMinor - $discountMinor); // AF-09: Net subtotal after discount
                $affiliateCommissionMinor = $affiliateTracking->calculateCommission(
                    link: $attributedLink,
                    subtotalMinor: $effectiveSubtotal,
                    variant: $lineItemsData[0]['variant'],
                    quantity: $lineItemsData[0]['quantity'],
                    lineItems: $lineItemsData
                );

                $attributedLink->increment('orders_count');
            }

            $priceSnapshot['affiliate_profit_minor'] = $affiliateCommissionMinor;
            $priceSnapshot['attribution_type'] = $attributedLinkId !== null ? 'tracking_link' : null;

            // 9. Enforce Plan Limits & Create Lunar Order (BL-04)
            $tenant = function_exists('tenant') && tenant('id') ? Tenant::find(tenant('id')) : null;
            if ($tenant instanceof Tenant) {
                Limits::ensure('monthly_orders', $tenant);
            }

            $storefrontCustomerId = $payload->customer_id ?? (Auth::guard('customer')->check() ? (int) Auth::guard('customer')->id() : null);

            /** @var Order $order */
            $order = Order::create([
                'storefront_customer_id' => $storefrontCustomerId,
                'channel_id' => $channelId,
                'status' => OrderStatus::SUBMITTED->value,
                'reference' => $publicRef,
                'customer_reference' => $publicRef,
                'sub_total' => $subtotalMinor,
                'discount_total' => $discountMinor,
                'shipping_total' => $shippingMinor,
                'tax_breakdown' => new TaxBreakdown,
                'tax_total' => 0,
                'total' => $totalMinor,
                'notes' => $payload->notes,
                'currency_code' => Money::defaultCurrency(),
                'compare_currency_code' => Money::defaultCurrency(),
                'exchange_rate' => 1.0,
                'placed_at' => now(),
                'affiliate_id' => $attributedAffiliateId,
                'tracking_link_id' => $attributedLinkId,
                'customer_phone_e164' => $phone->e164,
                'customer_phone_hash' => $phone->hash(),
                'governorate_id' => $payload->governorate_id,
                'cod_fee_minor' => $codFeeMinor,
                'payment_method' => $payload->payment_method,
                'idempotency_key' => $payload->idempotency_token,
                'price_snapshot' => json_encode($priceSnapshot),
                'quality_result' => $spamResult->status,
                'quality_flags' => json_encode($spamResult->flags),
                'meta' => [
                    'city' => $payload->city,
                    'address' => $payload->address,
                    'affiliate_commission_minor' => $affiliateCommissionMinor,
                    'self_referral_blocked' => $selfReferralBlocked,
                    'spam_check' => [
                        'status' => $spamResult->status,
                        'is_duplicate' => $spamResult->isDuplicate,
                        'reason' => $spamResult->reasonAr,
                    ],
                ],
            ]);

            // 10. Create Order Lines
            foreach ($lineItemsData as $item) {
                OrderLine::create([
                    'order_id' => $order->id,
                    'purchasable_type' => ProductVariant::class,
                    'purchasable_id' => $item['variant']->id,
                    'type' => 'physical',
                    'description' => $item['product_name'],
                    'identifier' => $item['sku'],
                    'unit_price' => $item['unit_price_minor'],
                    'unit_quantity' => 1,
                    'quantity' => $item['quantity'],
                    'sub_total' => $item['subtotal_minor'],
                    'discount_total' => 0,
                    'tax_breakdown' => new TaxBreakdown,
                    'tax_total' => 0,
                    'total' => $item['subtotal_minor'],
                ]);
            }

            // 11. Create Shipping OrderAddress
            OrderAddress::create([
                'order_id' => $order->id,
                'first_name' => $payload->customer_name,
                'last_name' => '',
                'line_one' => $payload->address,
                'city' => $payload->city,
                'contact_phone' => $phone->national,
                'delivery_instructions' => $payload->notes,
                'type' => 'shipping',
                'shipping_option' => 'standard',
            ]);

            // 12. Record coupon redemption if applied
            if ($coupon !== null) {
                $this->couponService->recordRedemption(
                    $coupon,
                    $order->id,
                    $phone->e164,
                    $discountMinor
                );
            }

            // 12.1 Record promotions allocations
            app(PromotionPricingService::class)->recordOrderPromotions($order);

            // 13. Process payment through gateway
            $gateway = $this->paymentManager->get($payload->payment_method);
            $paymentResult = $gateway->process($order);

            if ($paymentResult->isRedirect && $paymentResult->redirectUrl !== null) {
                /** @var mixed $rawMeta */
                $rawMeta = $order->meta;
                /** @var array<string, mixed> $currentMeta */
                $currentMeta = is_array($rawMeta) ? $rawMeta : (is_object($rawMeta) && method_exists($rawMeta, 'toArray') ? $rawMeta->toArray() : []);
                $currentMeta['payment_redirect_url'] = $paymentResult->redirectUrl;
                $currentMeta['payment_transaction_ref'] = $paymentResult->transactionRef;
                $order->meta = $currentMeta;
                $order->save();
            }

            $tenantId = tenant('id');
            $tenantIdString = is_string($tenantId) ? $tenantId : (string) ($order->getAttribute('tenant_id') ?? 'default');

            // 14. Initial submission audit event
            $submittedEvent = OrderEvent::create([
                'tenant_id' => $tenantIdString,
                'order_id' => $order->id,
                'from_status' => null,
                'to_status' => OrderStatus::SUBMITTED->value,
                'reason_code' => null,
                'actor_type' => 'customer',
                'actor_id' => $storefrontCustomerId !== null ? (string) $storefrontCustomerId : null,
                'actor_name' => $payload->customer_name,
                'note' => 'تم استلام الطلب من المتجر الإلكتروني',
                'meta' => [
                    'ip' => request()->ip(),
                    'quality_result' => $spamResult->status,
                ],
                'created_at' => now(),
            ]);

            Event::dispatch(new OrderSubmitted($order, $submittedEvent, null, OrderActor::customer($storefrontCustomerId, $payload->customer_name)));

            // 15. Transition based on Quality Gate check
            if ($spamResult->status === 'reject') {
                $this->orderTransitionService->transition(
                    $order,
                    OrderStatus::REJECTED_QUALITY,
                    ReasonCode::SUSPECTED_FRAUD,
                    OrderActor::system(),
                    ['flags' => $spamResult->flags],
                    $spamResult->reasonAr
                );
            } else {
                $this->orderTransitionService->transition(
                    $order,
                    OrderStatus::PENDING_CONFIRMATION,
                    null,
                    OrderActor::system(),
                    ['flags' => $spamResult->flags]
                );
            }

            Log::info("Order placed: {$order->reference} | Phone: {$phone->e164} | Total: {$totalMinor} piasters | Quality: {$spamResult->status}");

            return $order->fresh() ?? $order;
        });
    }
}
