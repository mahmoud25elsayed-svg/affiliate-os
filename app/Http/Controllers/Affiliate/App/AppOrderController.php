<?php

declare(strict_types=1);

namespace App\Http\Controllers\Affiliate\App;

use App\Domain\Affiliate\Actions\CancelAffiliateOrderAction;
use App\Domain\Affiliate\Actions\PriceQuoteAction;
use App\Domain\Affiliate\Actions\SubmitAffiliateOrderAction;
use App\Domain\Affiliate\Settings\AffiliateProgramSettings;
use App\Domain\Orders\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Affiliate;
use App\Models\OrderEvent;
use App\Models\WholesalePrice;
use App\Support\Location\Governorate;
use App\Support\Money;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use Lunar\Models\Order;
use Lunar\Models\OrderAddress;
use Lunar\Models\Price;
use Lunar\Models\Product;
use Lunar\Models\ProductVariant;

class AppOrderController extends Controller
{
    public function index(Request $request): Response
    {
        /** @var Affiliate $affiliate */
        $affiliate = Auth::guard('affiliate')->user();
        $status = $request->query('status');

        $query = Order::query()->where('affiliate_id', $affiliate->id)->latest('id');

        if ($status) {
            if ($status === 'in_progress') {
                $query->whereNotIn('status', [
                    OrderStatus::DELIVERED->value,
                    OrderStatus::CANCELLED_BY_CUSTOMER->value,
                    OrderStatus::CANCELLED_NO_ANSWER->value,
                    OrderStatus::RETURN_REQUESTED->value,
                    OrderStatus::RETURNING->value,
                    OrderStatus::RETURNED->value,
                ]);
            } elseif ($status === 'cancelled') {
                $query->whereIn('status', [
                    OrderStatus::CANCELLED_BY_CUSTOMER->value,
                    OrderStatus::CANCELLED_NO_ANSWER->value,
                ]);
            } elseif ($status === 'returned') {
                $query->whereIn('status', [
                    OrderStatus::RETURN_REQUESTED->value,
                    OrderStatus::RETURNING->value,
                    OrderStatus::RETURNED->value,
                ]);
            } else {
                $query->where('status', $status);
            }
        }

        $orders = $query->paginate(15)->through(function ($order) {
            /** @var Order $order */
            $rawSnapshot = $order->getAttribute('price_snapshot');
            $snapshot = is_array($rawSnapshot) ? $rawSnapshot : json_decode(is_string($rawSnapshot) ? $rawSnapshot : '[]', true);
            if (! is_array($snapshot)) {
                $snapshot = [];
            }

            $shippingAddress = $order->shippingAddress;
            $firstName = ($shippingAddress instanceof OrderAddress) ? $shippingAddress->first_name : null;

            return [
                'id' => $order->id,
                'reference' => $order->reference ?? (string) $order->id,
                'status' => $order->status,
                'customer_name' => $firstName ?? ($snapshot['customer_name'] ?? 'عميل'),
                'customer_city' => $snapshot['customer_city'] ?? 'غير محدد',
                'customer_phone' => $snapshot['customer_phone'] ?? '***',
                'items_count' => (int) ($snapshot['quantity'] ?? 1),
                'total_minor' => Money::minorOf($order->total) ?: (int) ($snapshot['customer_total_minor'] ?? 0),
                'affiliate_profit_minor' => (int) ($snapshot['affiliate_profit_minor'] ?? 0),
                'created_at' => $order->created_at?->format('Y-m-d H:i') ?? '',
            ];
        });

        return Inertia::render('App/Orders', [
            'orders' => $orders,
            'currentStatus' => $status,
        ]);
    }

    public function create(Request $request): Response
    {
        $settings = AffiliateProgramSettings::load();
        $variantId = $request->query('variant_id');

        $products = Product::with(['variants.prices'])->get()->map(function (Product $p) use ($settings) {
            return [
                'id' => $p->id,
                'name' => $p->translateAttribute('name') ?? "منتج #{$p->id}",
                'variants' => $p->variants->map(function ($v) use ($settings) {
                    /** @var ProductVariant $v */
                    $wholesale = WholesalePrice::where('product_variant_id', $v->id)->first();
                    $price = $v->prices->first();
                    $retail = (int) ($price instanceof Price ? $price->price->value : 0);
                    $wholesaleMinor = (int) ($wholesale ? $wholesale->wholesale_price_minor : ($retail > 0 ? (int) round($retail * 0.7) : 10000));

                    return [
                        'id' => $v->id,
                        'sku' => $v->sku,
                        'stock' => $v->stock,
                        'wholesale_minor' => $wholesaleMinor,
                        'min_selling_minor' => $wholesaleMinor + $settings->min_selling_markup_minor,
                        'max_selling_minor' => $wholesaleMinor + $settings->max_selling_markup_minor,
                    ];
                })->all(),
            ];
        });

        $governorates = Governorate::with('cities')->get()->map(fn (Governorate $g) => [
            'id' => $g->id,
            'name' => $g->name_ar ?: $g->name_en,
            'cities' => $g->cities->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->name_ar ?: $c->name_en,
            ]),
        ]);

        return Inertia::render('App/CreateOrder', [
            'products' => $products,
            'governorates' => $governorates,
            'selectedVariantId' => $variantId ? (int) $variantId : null,
            'settings' => [
                'min_selling_markup_minor' => $settings->min_selling_markup_minor,
                'max_selling_markup_minor' => $settings->max_selling_markup_minor,
            ],
        ]);
    }

    public function quote(Request $request, PriceQuoteAction $priceQuoteAction): JsonResponse
    {
        $validated = $request->validate([
            'variant_id' => ['required', 'integer', 'exists:lunar_product_variants,id'],
            'quantity' => ['required', 'integer', 'min:1'],
            'selling_price_minor' => ['required', 'integer', 'min:1'],
            'governorate_id' => ['nullable', 'integer'],
        ]);

        /** @var ProductVariant $variant */
        $variant = ProductVariant::with('wholesalePrice')->findOrFail($validated['variant_id']);
        $govId = ! empty($validated['governorate_id']) ? (int) $validated['governorate_id'] : 1;

        $result = $priceQuoteAction->execute(
            variant: $variant,
            quantity: (int) $validated['quantity'],
            sellingPriceMinor: (int) $validated['selling_price_minor'],
            governorateId: $govId
        );

        return response()->json([
            'wholesale_total_minor' => $result->wholesaleUnitPriceMinor * $result->quantity,
            'shipping_cost_minor' => $result->shippingCostMinor,
            'cod_fee_minor' => $result->codFeeMinor,
            'affiliate_profit_minor' => $result->affiliateProfitMinor,
            'customer_total_minor' => $result->customerTotalMinor,
            'warnings' => $result->warnings,
        ]);
    }

    public function store(Request $request, SubmitAffiliateOrderAction $submitAction): RedirectResponse
    {
        /** @var Affiliate $affiliate */
        $affiliate = Auth::guard('affiliate')->user();

        $validated = $request->validate([
            'variant_id' => ['required', 'integer', 'exists:lunar_product_variants,id'],
            'quantity' => ['required', 'integer', 'min:1'],
            'selling_price_minor' => ['required', 'integer', 'min:1'],
            'customer_name' => ['required', 'string', 'max:100'],
            'customer_phone' => ['required', 'string'],
            'customer_email' => ['nullable', 'email'],
            'governorate_id' => ['required', 'integer', 'exists:governorates,id'],
            'city_id' => ['nullable', 'integer'],
            'address' => ['required', 'string', 'min:5', 'max:255'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $order = $submitAction->execute($affiliate, [
                'customer_name' => $validated['customer_name'],
                'customer_phone' => $validated['customer_phone'],
                'governorate_id' => (int) $validated['governorate_id'],
                'city' => 'القاهرة',
                'address' => $validated['address'],
                'notes' => $validated['notes'] ?? null,
                'items' => [
                    [
                        'variant_id' => (int) $validated['variant_id'],
                        'quantity' => (int) $validated['quantity'],
                        'selling_price_minor' => (int) $validated['selling_price_minor'],
                    ],
                ],
                'idempotency_key' => bin2hex(random_bytes(16)),
                'ip_address' => $request->ip(),
            ]);

            return redirect()->route('affiliate.app.orders.show', $order->reference)
                ->with('success', "تم تسجيل الطلب #{$order->reference} بنجاح وفحص جودته.");
        } catch (\Exception $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function show(int|string $id): Response
    {
        /** @var Affiliate $affiliate */
        $affiliate = Auth::guard('affiliate')->user();
        $settings = AffiliateProgramSettings::load();

        /** @var Order $order */
        $order = Order::where('affiliate_id', $affiliate->id)
            ->where(function ($q) use ($id) {
                if (is_numeric($id)) {
                    $q->where('id', (int) $id)->orWhere('reference', (string) $id);
                } else {
                    $q->where('reference', (string) $id);
                }
            })
            ->with(['lines.purchasable', 'shippingAddress'])
            ->firstOrFail();

        $rawSnapshot = $order->getAttribute('price_snapshot');
        $snapshot = is_array($rawSnapshot) ? $rawSnapshot : json_decode(is_string($rawSnapshot) ? $rawSnapshot : '[]', true);
        if (! is_array($snapshot)) {
            $snapshot = [];
        }

        // Fetch Order Events Timeline
        $events = OrderEvent::query()
            ->where('order_id', $order->id)
            ->latest('id')
            ->get()
            ->map(function ($ev) {
                /** @var OrderEvent $ev */
                return [
                    'id' => $ev->id,
                    'event_type' => $ev->to_status,
                    'description' => $ev->note ?? "تغيير الحالة إلى {$ev->to_status}",
                    'reason_code' => $ev->reason_code?->value,
                    'created_at' => $ev->created_at->format('Y-m-d H:i'),
                ];
            });

        $phone = (string) ($snapshot['customer_phone'] ?? '');
        if (! $settings->shouldShowCustomerPhone()) {
            $phone = substr($phone, 0, 4).'****'.substr($phone, -3);
        }

        $canCancel = in_array($order->status, [
            OrderStatus::SUBMITTED->value,
            OrderStatus::PENDING_CONFIRMATION->value,
            OrderStatus::CONFIRMED->value,
            OrderStatus::RESERVED->value,
        ], true);

        $shippingAddress = $order->shippingAddress;
        $firstName = ($shippingAddress instanceof OrderAddress) ? $shippingAddress->first_name : null;

        return Inertia::render('App/OrderDetail', [
            'order' => [
                'id' => $order->id,
                'reference' => $order->reference ?? (string) $order->id,
                'status' => $order->status,
                'customer_name' => $firstName ?? ($snapshot['customer_name'] ?? 'عميل'),
                'customer_phone' => $phone,
                'customer_governorate' => $snapshot['customer_governorate'] ?? '',
                'customer_city' => $snapshot['customer_city'] ?? '',
                'customer_address' => $snapshot['customer_address'] ?? '',
                'product_name' => $snapshot['product_name'] ?? 'المنتج',
                'quantity' => (int) ($snapshot['quantity'] ?? 1),
                'selling_price_minor' => (int) ($snapshot['selling_price_minor'] ?? 0),
                'wholesale_price_minor' => (int) ($snapshot['wholesale_price_minor'] ?? 0),
                'shipping_cost_minor' => (int) ($snapshot['shipping_cost_minor'] ?? 0),
                'customer_total_minor' => (int) ($snapshot['customer_total_minor'] ?? 0),
                'affiliate_profit_minor' => (int) ($snapshot['affiliate_profit_minor'] ?? 0),
                'created_at' => $order->created_at?->format('Y-m-d H:i') ?? '',
                'can_cancel' => $canCancel,
            ],
            'timeline' => $events,
        ]);
    }

    public function cancel(int|string $id, CancelAffiliateOrderAction $cancelAction): RedirectResponse
    {
        /** @var Affiliate $affiliate */
        $affiliate = Auth::guard('affiliate')->user();

        /** @var Order $order */
        $order = Order::where('affiliate_id', $affiliate->id)
            ->where(function ($q) use ($id) {
                if (is_numeric($id)) {
                    $q->where('id', (int) $id)->orWhere('reference', (string) $id);
                } else {
                    $q->where('reference', (string) $id);
                }
            })
            ->firstOrFail();

        try {
            $cancelAction->execute($affiliate, $order, 'إلغاء بناء على طلب المسوق');

            return back()->with('success', 'تم إلغاء الطلب بنجاح.');
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
