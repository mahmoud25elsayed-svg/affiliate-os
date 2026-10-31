<?php

declare(strict_types=1);

namespace App\Livewire\Storefront;

use App\Domain\Checkout\DTOs\CheckoutPayload;
use App\Domain\Checkout\DTOs\ShippingQuote;
use App\Domain\Checkout\Services\CouponService;
use App\Domain\Checkout\Services\OrderCreationService;
use App\Domain\Checkout\Services\PaymentManager;
use App\Domain\Checkout\Services\ShippingCalculatorService;
use App\Domain\Checkout\Services\StorefrontCartService;
use App\Models\Coupon;
use App\Support\Location\Governorate;
use App\Support\Settings\ShippingSettings;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Component;

class CheckoutPage extends Component
{
    public string $customer_name = '';

    public string $customer_phone = '';

    public ?int $governorate_id = null;

    public string $city = '';

    public string $address = '';

    public string $notes = '';

    public string $payment_method = 'cod';

    public string $coupon_code = '';

    public ?string $applied_coupon_code = null;

    public int $discount_minor = 0;

    public bool $is_free_shipping = false;

    public ?string $coupon_error = null;

    public ?string $coupon_success = null;

    public string $idempotency_token = '';

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        $gateways = array_keys(app(PaymentManager::class)->getActiveGatewaysForTenant());
        $gatewaysList = ! empty($gateways) ? implode(',', $gateways) : 'cod';

        return [
            'customer_name' => 'required|string|min:3|max:100',
            'customer_phone' => 'required|string',
            'governorate_id' => 'required|integer|exists:governorates,id',
            'city' => 'required|string|min:2|max:100',
            'address' => 'required|string|min:5|max:255',
            'notes' => 'nullable|string|max:500',
            'payment_method' => 'required|string|in:'.$gatewaysList,
        ];
    }

    /**
     * @var array<string, string>
     */
    protected $messages = [
        'customer_name.required' => 'يرجى إدخال الاسم بالكامل.',
        'customer_name.min' => 'يجب أن يتكون الاسم من 3 أحرف على الأقل.',
        'customer_phone.required' => 'يرجى إدخال رقم الهاتف.',
        'governorate_id.required' => 'يرجى اختيار المحافظة.',
        'governorate_id.exists' => 'المحافظة المختارة غير صالحة.',
        'city.required' => 'يرجى إدخال المدينة أو المنطقة.',
        'address.required' => 'يرجى إدخال العنوان بالتفصيل.',
    ];

    public function mount(): void
    {
        $this->idempotency_token = (string) Str::uuid();

        /** @var Governorate|null $firstGov */
        $firstGov = Governorate::where('is_active', true)->orderBy('name_ar')->first();
        if ($firstGov) {
            $this->governorate_id = $firstGov->id;
        }

        // Logged-in customers should not retype what their account already holds.
        $customer = Auth::guard('customer')->user();
        if ($customer !== null) {
            $this->customer_name = trim($customer->getAttribute('first_name').' '.$customer->getAttribute('last_name'));
            $e164 = (string) $customer->getAttribute('phone_e164');
            $this->customer_phone = str_starts_with($e164, '+20') ? '0'.substr($e164, 3) : $e164;
        }
    }

    public function updatedGovernorateId(): void
    {
        // Re-evaluates shipping quote reactively
    }

    public function applyCoupon(): void
    {
        $this->coupon_error = null;
        $this->coupon_success = null;

        $cartService = app(StorefrontCartService::class);
        $subtotalMinor = $cartService->getSubtotalMinor();
        $items = $cartService->getItems();

        if (empty($items)) {
            $this->coupon_error = 'سلة المشتريات فارغة.';

            return;
        }

        $itemIds = array_map(fn ($item) => $item['variant_id'], $items);

        $couponService = app(CouponService::class);
        $result = $couponService->validateAndCalculate(
            $this->coupon_code,
            $subtotalMinor,
            $itemIds,
            [],
            $this->customer_phone !== '' ? $this->customer_phone : null
        );

        if (! $result->isValid) {
            $this->coupon_error = $result->errorMessage;
            $this->discount_minor = 0;
            $this->is_free_shipping = false;
            $this->applied_coupon_code = null;

            return;
        }

        $this->applied_coupon_code = $result->coupon?->code;
        $this->discount_minor = $result->discountMinor;
        $this->is_free_shipping = $result->isFreeShipping;
        $this->coupon_success = 'تم تفعيل الكوبون بنجاح!';
    }

    public function removeCoupon(): void
    {
        $this->applied_coupon_code = null;
        $this->coupon_code = '';
        $this->discount_minor = 0;
        $this->is_free_shipping = false;
        $this->coupon_error = null;
        $this->coupon_success = null;
    }

    public function submitOrder(OrderCreationService $orderCreationService): mixed
    {
        $this->validate();

        $cartService = app(StorefrontCartService::class);
        $rawCart = $cartService->getRawCart();

        if (empty($rawCart)) {
            $this->addError('cart', 'سلة المشتريات فارغة لا يمكن إتمام الطلب.');

            return null;
        }

        $itemsPayload = [];
        foreach ($rawCart as $variantId => $qty) {
            $itemsPayload[] = [
                'purchasable_id' => $variantId,
                'quantity' => $qty,
            ];
        }

        $payload = new CheckoutPayload(
            customer_name: $this->customer_name,
            customer_phone: $this->customer_phone,
            governorate_id: (int) $this->governorate_id,
            city: $this->city,
            address: $this->address,
            notes: $this->notes !== '' ? $this->notes : null,
            payment_method: $this->payment_method,
            coupon_code: $this->applied_coupon_code,
            items: $itemsPayload,
            idempotency_token: $this->idempotency_token
        );

        $order = $orderCreationService->create($payload);

        // Clear cart session
        $cartService->clear();

        /** @var mixed $rawMeta */
        $rawMeta = $order->meta;
        /** @var array<string, mixed> $meta */
        $meta = is_array($rawMeta) ? $rawMeta : (is_object($rawMeta) && method_exists($rawMeta, 'toArray') ? $rawMeta->toArray() : []);
        $redirectUrl = $meta['payment_redirect_url'] ?? null;

        if (is_string($redirectUrl) && $redirectUrl !== '') {
            return redirect()->to($redirectUrl);
        }

        return redirect()->route('storefront.checkout.success', ['reference' => $order->reference]);
    }

    public function render(): View
    {
        $cartService = app(StorefrontCartService::class);
        $items = $cartService->getItems();
        $count = $cartService->getCount();
        $subtotalMinor = $cartService->getSubtotalMinor();

        $governorates = Governorate::where('is_active', true)->orderBy('name_ar')->get();

        // Calculate shipping quote
        $shippingQuote = new ShippingQuote(0, 'غير متاح', false);
        if ($this->governorate_id !== null) {
            $coupon = null;
            if ($this->applied_coupon_code !== null) {
                /** @var Coupon|null $coupon */
                $coupon = Coupon::where('code', $this->applied_coupon_code)->first();
            }

            $calculator = app(ShippingCalculatorService::class);
            $shippingQuote = $calculator->calculate(
                $this->governorate_id,
                $subtotalMinor,
                $coupon
            );
        }

        // Calculate COD fee
        $settings = ShippingSettings::load();
        $codFeeMinor = ($this->payment_method === 'cod' && $settings->allow_cod) ? $settings->cod_fee_minor : 0;

        // Calculate Total
        $discountedSubtotal = max(0, $subtotalMinor - $this->discount_minor);
        $totalMinor = $discountedSubtotal + $shippingQuote->rate_minor + $codFeeMinor;

        $activeGateways = app(PaymentManager::class)->getActiveGatewaysForTenant();

        return view('livewire.storefront.checkout-page', [
            'items' => $items,
            'count' => $count,
            'subtotalDecimal' => number_format($subtotalMinor / 100, 2),
            'discountDecimal' => number_format($this->discount_minor / 100, 2),
            'shippingQuote' => $shippingQuote,
            'shippingDecimal' => number_format($shippingQuote->rate_minor / 100, 2),
            'codFeeDecimal' => number_format($codFeeMinor / 100, 2),
            'totalDecimal' => number_format($totalMinor / 100, 2),
            'governorates' => $governorates,
            'settings' => $settings,
            'activeGateways' => $activeGateways,
        ])->layout('layouts.storefront', [
            'title' => 'إتمام الطلب',
        ]);
    }
}
