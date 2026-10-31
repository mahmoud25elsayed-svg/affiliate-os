<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Domain\Affiliate\Actions\PriceQuoteAction;
use App\Domain\Affiliate\Actions\SubmitAffiliateOrderAction;
use App\Domain\Affiliate\Settings\AffiliateProgramSettings;
use App\Domain\Tracking\Services\AdTrackingManager;
use App\Domain\Webhooks\Services\WebhookDispatcher;
use App\Http\Controllers\Controller;
use App\Models\AdConversionEvent;
use App\Models\Affiliate;
use App\Models\WholesalePrice;
use App\Support\Location\Governorate;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Lunar\Models\Price;
use Lunar\Models\Product;
use Lunar\Models\ProductVariant;

class EmbedOrderController extends Controller
{
    public function __construct(
        protected PriceQuoteAction $priceQuoteAction,
        protected SubmitAffiliateOrderAction $submitAction,
        protected WebhookDispatcher $webhookDispatcher,
        protected AdTrackingManager $trackingManager
    ) {}

    public function show(Request $request, Product $product): View
    {
        $product->loadMissing(['variants.prices', 'variants.wholesalePrice', 'media']);

        $governorates = Governorate::forCountries();
        $refCode = $request->query('ref');
        $affiliate = $refCode ? Affiliate::where('code', $refCode)->first() : null;

        $firstVariant = $product->variants->first();
        $settings = AffiliateProgramSettings::load();

        return view('storefront.embed.order-form', [
            'product' => $product,
            'firstVariant' => $firstVariant,
            'governorates' => $governorates,
            'affiliate' => $affiliate,
            'refCode' => $refCode,
            'utmSource' => $request->query('utm_source'),
            'utmMedium' => $request->query('utm_medium'),
            'utmCampaign' => $request->query('utm_campaign'),
            'utmContent' => $request->query('utm_content'),
            'settings' => $settings,
        ]);
    }

    public function quote(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'variant_id' => ['required', 'integer'],
            'quantity' => ['required', 'integer', 'min:1'],
            'governorate_id' => ['required', 'integer'],
        ]);

        /** @var ProductVariant $variant */
        $variant = ProductVariant::with(['wholesalePrice', 'prices'])->findOrFail($validated['variant_id']);

        /** @var WholesalePrice|null $wholesale */
        $wholesale = $variant->getRelationValue('wholesalePrice');
        /** @var Price|null $retailPrice */
        $retailPrice = $variant->prices->first();
        $retailMinor = $retailPrice ? (int) $retailPrice->price->value : 25000;
        $unitPriceMinor = $wholesale
            ? ($wholesale->compare_at_price_minor ?? $wholesale->min_price_minor ?? $retailMinor)
            : $retailMinor;

        try {
            $quote = $this->priceQuoteAction->execute(
                variant: $variant,
                quantity: (int) $validated['quantity'],
                sellingPriceMinor: $unitPriceMinor,
                governorateId: (int) $validated['governorate_id']
            );

            return response()->json([
                'success' => true,
                'quote' => $quote->toArray(),
                'formatted_total' => number_format($quote->customerTotalMinor / 100, 2),
                'formatted_shipping' => number_format($quote->shippingCostMinor / 100, 2),
            ]);
        } catch (DomainException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function store(Request $request, Product $product): JsonResponse|View|RedirectResponse
    {
        $validated = $request->validate([
            'variant_id' => ['required', 'integer'],
            'quantity' => ['required', 'integer', 'min:1', 'max:20'],
            'customer_name' => ['required', 'string', 'max:150'],
            'customer_phone' => ['required', 'string', 'max:30'],
            'governorate_id' => ['required', 'integer', 'exists:governorates,id'],
            'city' => ['required', 'string', 'max:100'],
            'address' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:500'],
            'ref' => ['nullable', 'string', 'max:50'],
            'utm_source' => ['nullable', 'string', 'max:100'],
            'utm_medium' => ['nullable', 'string', 'max:100'],
            'utm_campaign' => ['nullable', 'string', 'max:100'],
            'utm_content' => ['nullable', 'string', 'max:100'],
        ]);

        /** @var ProductVariant $variant */
        $variant = ProductVariant::with(['wholesalePrice', 'prices'])->findOrFail($validated['variant_id']);

        // Resolve Affiliate by ref code if present
        $affiliate = ! empty($validated['ref'])
            ? Affiliate::where('code', $validated['ref'])->first()
            : Affiliate::first();

        if (! $affiliate) {
            /** @var Affiliate $affiliate */
            $affiliate = Affiliate::firstOrCreate(
                ['code' => 'STORE-DIRECT'],
                ['name' => 'المتجر المباشر', 'phone' => '+201000000000', 'status' => 'approved', 'balance_minor' => 0]
            );
        }

        /** @var WholesalePrice|null $wholesale */
        $wholesale = $variant->getRelationValue('wholesalePrice');
        /** @var Price|null $retailPrice */
        $retailPrice = $variant->prices->first();
        $retailMinor = $retailPrice ? (int) $retailPrice->price->value : 25000;
        $unitPriceMinor = $wholesale
            ? ($wholesale->compare_at_price_minor ?? $wholesale->min_price_minor ?? $retailMinor)
            : $retailMinor;

        try {
            $order = $this->submitAction->execute($affiliate, [
                'customer_name' => $validated['customer_name'],
                'customer_phone' => $validated['customer_phone'],
                'governorate_id' => (int) $validated['governorate_id'],
                'city' => $validated['city'],
                'address' => $validated['address'],
                'notes' => $validated['notes'] ?? null,
                'items' => [
                    [
                        'variant_id' => (int) $variant->id,
                        'quantity' => (int) $validated['quantity'],
                        'selling_price_minor' => $unitPriceMinor,
                    ],
                ],
                'utm_source' => $validated['utm_source'] ?? null,
                'utm_medium' => $validated['utm_medium'] ?? null,
                'utm_campaign' => $validated['utm_campaign'] ?? null,
                'utm_content' => $validated['utm_content'] ?? null,
            ]);

            // Dispatch webhook
            $this->webhookDispatcher->dispatch('order.created', [
                'order_id' => $order->id,
                'reference' => $order->reference,
                'affiliate_id' => $affiliate->id,
                'total_minor' => (int) ($order->getRawOriginal('total') ?? 0),
                'channel' => 'embed_form',
                'created_at' => now()->toIso8601String(),
            ]);

            // Dispatch server-side Conversions API (Meta CAPI & TikTok Events API)
            $eventId = $this->trackingManager->resolveOrderEventId($order, AdConversionEvent::EVENT_PURCHASE);
            $this->trackingManager->trackOrderPurchase(
                order: $order,
                eventId: $eventId,
                clientContext: [
                    'ip' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'fbp' => $request->cookie('_fbp'),
                    'fbc' => $request->cookie('_fbc'),
                    'ttclid' => $request->cookie('ttclid'),
                ]
            );

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'reference' => $order->reference,
                    'event_id' => $eventId,
                    'message' => 'تم استلام طلبك بنجاح!',
                ], 201);
            }

            return view('storefront.embed.success', [
                'order' => $order,
                'eventId' => $eventId,
            ]);
        } catch (DomainException $e) {
            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                ], 422);
            }

            return back()->withInput()->withErrors(['order' => $e->getMessage()]);
        }
    }
}
