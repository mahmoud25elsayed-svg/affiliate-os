<?php

declare(strict_types=1);

namespace App\Http\Controllers\Affiliate;

use App\Domain\Affiliate\Actions\CancelAffiliateOrderAction;
use App\Domain\Affiliate\Actions\PriceQuoteAction;
use App\Domain\Affiliate\Actions\SubmitAffiliateOrderAction;
use App\Domain\Affiliate\Services\AffiliateOrderCsvImportService;
use App\Domain\Affiliate\Settings\AffiliateProgramSettings;
use App\Domain\Orders\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Affiliate;
use App\Support\Location\Governorate;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Lunar\Models\Order;
use Lunar\Models\ProductVariant;

class AffiliateOrderController extends Controller
{
    public function index(Request $request): View
    {
        /** @var Affiliate $affiliate */
        $affiliate = $request->user('affiliate');

        $query = Order::query()
            ->where('affiliate_id', $affiliate->id)
            ->with(['lines.purchasable', 'shippingAddress'])
            ->latest();

        if ($request->filled('status')) {
            $status = (string) $request->input('status');
            $query->where('status', $status);
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('reference', 'like', "%{$search}%")
                    ->orWhere('customer_phone_e164', 'like', "%{$search}%");
            });
        }

        $orders = $query->paginate(15)->withQueryString();

        return view('affiliate.orders.index', [
            'orders' => $orders,
            'currentStatus' => $request->input('status'),
        ]);
    }

    public function create(Request $request): View
    {
        $governorates = Governorate::query()->orderBy('name_ar')->get();
        $selectedVariant = null;

        if ($request->filled('variant_id')) {
            $selectedVariant = ProductVariant::with(['wholesalePrice', 'product'])->find($request->input('variant_id'));
        }

        $variants = ProductVariant::query()
            ->whereHas('wholesalePrice', fn ($q) => $q->where('status', 'active'))
            ->with(['wholesalePrice', 'product'])
            ->get();

        $settings = AffiliateProgramSettings::load();

        return view('affiliate.orders.create', [
            'governorates' => $governorates,
            'selectedVariant' => $selectedVariant,
            'variants' => $variants,
            'settings' => $settings,
        ]);
    }

    public function quote(Request $request, PriceQuoteAction $priceQuoteAction): JsonResponse
    {
        $validated = $request->validate([
            'variant_id' => 'required|integer|exists:lunar_product_variants,id',
            'quantity' => 'required|integer|min:1',
            'selling_price' => 'required|numeric|min:1',
            'governorate_id' => 'required|integer|exists:governorates,id',
        ]);

        /** @var ProductVariant $variant */
        $variant = ProductVariant::with('wholesalePrice')->findOrFail($validated['variant_id']);
        $sellingPriceMinor = (int) round(((float) $validated['selling_price']) * 100);

        try {
            $result = $priceQuoteAction->execute(
                variant: $variant,
                quantity: (int) $validated['quantity'],
                sellingPriceMinor: $sellingPriceMinor,
                governorateId: (int) $validated['governorate_id']
            );

            return response()->json([
                'success' => true,
                'quote' => $result->toArray(),
                'formatted' => [
                    'wholesale_price' => number_format($result->wholesaleUnitPriceMinor / 100, 2),
                    'selling_price' => number_format($result->sellingUnitPriceMinor / 100, 2),
                    'subtotal' => number_format($result->subtotalMinor / 100, 2),
                    'shipping_cost' => number_format($result->shippingCostMinor / 100, 2),
                    'cod_fee' => number_format($result->codFeeMinor / 100, 2),
                    'affiliate_profit' => number_format($result->affiliateProfitMinor / 100, 2),
                    'customer_total' => number_format($result->customerTotalMinor / 100, 2),
                ],
            ]);
        } catch (DomainException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function store(Request $request, SubmitAffiliateOrderAction $submitAction): RedirectResponse
    {
        /** @var Affiliate $affiliate */
        $affiliate = $request->user('affiliate');

        $validated = $request->validate([
            'customer_name' => 'required|string|max:150',
            'customer_phone' => 'required|string|max:30',
            'customer_alt_phone' => 'nullable|string|max:30',
            'governorate_id' => 'required|integer|exists:governorates,id',
            'city' => 'required|string|max:100',
            'address' => 'required|string|max:255',
            'building_number' => 'nullable|string|max:50',
            'floor' => 'nullable|string|max:50',
            'flat' => 'nullable|string|max:50',
            'notes' => 'nullable|string|max:500',
            'variant_id' => 'required|integer|exists:lunar_product_variants,id',
            'quantity' => 'required|integer|min:1|max:50',
            'selling_price' => 'required|numeric|min:1',
        ]);

        $sellingPriceMinor = (int) round(((float) $validated['selling_price']) * 100);

        try {
            $order = $submitAction->execute($affiliate, [
                'customer_name' => $validated['customer_name'],
                'customer_phone' => $validated['customer_phone'],
                'customer_alt_phone' => $validated['customer_alt_phone'] ?? null,
                'governorate_id' => (int) $validated['governorate_id'],
                'city' => $validated['city'],
                'address' => $validated['address'],
                'building_number' => $validated['building_number'] ?? null,
                'floor' => $validated['floor'] ?? null,
                'flat' => $validated['flat'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'items' => [
                    [
                        'variant_id' => (int) $validated['variant_id'],
                        'quantity' => (int) $validated['quantity'],
                        'selling_price_minor' => $sellingPriceMinor,
                    ],
                ],
                'idempotency_key' => $request->input('idempotency_key') ?? bin2hex(random_bytes(16)),
                'ip_address' => $request->ip(),
            ]);

            return redirect()->route('affiliate.orders.show', ['reference' => $order->reference])
                ->with('success', 'تم تسجيل الطلب بنجاح وهو الآن قيد المتابعة والتجهيز.');
        } catch (DomainException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function show(Request $request, string $reference): View
    {
        /** @var Affiliate $affiliate */
        $affiliate = $request->user('affiliate');

        /** @var Order $order */
        $order = Order::query()
            ->where('affiliate_id', $affiliate->id)
            ->where('reference', $reference)
            ->with(['lines.purchasable', 'shippingAddress'])
            ->firstOrFail();

        $settings = AffiliateProgramSettings::load();
        $showPhone = $settings->shouldShowCustomerPhone();

        $phone = (string) $order->getAttribute('customer_phone_e164');
        $displayedPhone = $showPhone ? $phone : (strlen($phone) > 7 ? substr($phone, 0, 4).'****'.substr($phone, -3) : 'محجوب');

        $canCancel = ! in_array($order->status, [
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
        ], true);

        return view('affiliate.orders.show', [
            'order' => $order,
            'displayedPhone' => $displayedPhone,
            'canCancel' => $canCancel,
        ]);
    }

    public function cancel(Request $request, string $reference, CancelAffiliateOrderAction $cancelAction): RedirectResponse
    {
        /** @var Affiliate $affiliate */
        $affiliate = $request->user('affiliate');

        /** @var Order $order */
        $order = Order::query()
            ->where('affiliate_id', $affiliate->id)
            ->where('reference', $reference)
            ->firstOrFail();

        $reason = $request->input('reason');

        try {
            $cancelAction->execute($affiliate, $order, is_string($reason) ? $reason : null);

            return back()->with('success', 'تم إلغاء الطلب بنجاح.');
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function showImportCsv(): View
    {
        return view('affiliate.orders.import-csv');
    }

    public function importCsv(Request $request, AffiliateOrderCsvImportService $importService): View
    {
        /** @var Affiliate $affiliate */
        $affiliate = $request->user('affiliate');

        $request->validate([
            'csv_file' => 'required|file|mimes:csv,txt|max:5120',
        ]);

        $file = $request->file('csv_file');
        if (! $file) {
            abort(400, 'الملف غير موجود');
        }

        try {
            $result = $importService->import($affiliate, $file);

            return view('affiliate.orders.import-csv', [
                'result' => $result,
            ]);
        } catch (DomainException $e) {
            return view('affiliate.orders.import-csv', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
