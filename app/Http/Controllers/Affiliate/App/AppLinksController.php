<?php

declare(strict_types=1);

namespace App\Http\Controllers\Affiliate\App;

use App\Domain\Affiliate\Services\TrackingLinkPriceValidator;
use App\Domain\Affiliate\Settings\AffiliateProgramSettings;
use App\Http\Controllers\Controller;
use App\Models\Affiliate;
use App\Models\TrackingLink;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Lunar\Models\Product;
use Lunar\Models\ProductVariant;

class AppLinksController extends Controller
{
    public function index(): Response
    {
        /** @var Affiliate $affiliate */
        $affiliate = Auth::guard('affiliate')->user();
        $settings = AffiliateProgramSettings::load();

        $links = TrackingLink::query()
            ->where('affiliate_id', $affiliate->id)
            ->with(['product'])
            ->latest('id')
            ->get()
            ->map(function (TrackingLink $link) use ($affiliate) {
                $targetUrl = url("/storefront?ref={$affiliate->code}&link={$link->code}");
                $qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=250x250&data='.urlencode($targetUrl);

                return [
                    'id' => $link->id,
                    'code' => $link->code,
                    'title' => $link->title,
                    'product_id' => $link->product_id,
                    'product_name' => $link->product?->translateAttribute('name') ?? 'رابط المتجر العام',
                    'custom_price_minor' => $link->custom_selling_price_minor,
                    'clicks_count' => (int) $link->clicks_count,
                    'orders_count' => (int) $link->orders_count,
                    'conversion_rate' => $link->clicks_count > 0 ? round(($link->orders_count / $link->clicks_count) * 100, 1) : 0.0,
                    'is_active' => (bool) $link->is_active,
                    'target_url' => $targetUrl,
                    'qr_url' => $qrUrl,
                    'created_at' => $link->created_at?->format('Y-m-d H:i') ?? '',
                ];
            });

        $products = Product::query()
            ->with(['variants.prices'])
            ->take(30)
            ->get()
            ->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->translateAttribute('name') ?? "منتج #{$p->id}",
            ]);

        $defaultStorefrontUrl = url("/storefront?ref={$affiliate->code}");

        return Inertia::render('App/Links', [
            'links' => $links,
            'products' => $products,
            'defaultStorefrontUrl' => $defaultStorefrontUrl,
            'defaultQrUrl' => 'https://api.qrserver.com/v1/create-qr-code/?size=250x250&data='.urlencode($defaultStorefrontUrl),
            'settings' => [
                'min_selling_markup_minor' => $settings->min_selling_markup_minor,
                'max_selling_markup_minor' => $settings->max_selling_markup_minor,
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        /** @var Affiliate $affiliate */
        $affiliate = Auth::guard('affiliate')->user();
        $settings = AffiliateProgramSettings::load();

        $validated = $request->validate([
            'product_id' => 'nullable|integer|exists:lunar_products,id',
            'title' => 'nullable|string|max:100',
            'custom_selling_price' => TrackingLinkPriceValidator::INPUT_RULE,
            'custom_code' => 'nullable|string|alpha_dash|max:32',
        ], [
            'custom_code.alpha_dash' => 'كود الرابط المخصص يجب أن يحتوي على أحرف وأرقام وشرطات فقط.',
        ]);

        $productId = ! empty($validated['product_id']) ? (int) $validated['product_id'] : null;
        $productVariantId = null;
        $customSellingPriceMinor = null;

        if ($productId !== null) {
            /** @var Product $product */
            $product = Product::with(['variants'])->findOrFail($productId);
            /** @var ProductVariant|null $firstVariant */
            $firstVariant = $product->variants->first();
            if ($firstVariant !== null) {
                $productVariantId = $firstVariant->id;
            }

            if (! empty($validated['custom_selling_price'])) {
                // AF-20: integer minor math, never floats
                $customSellingPriceMinor = app(TrackingLinkPriceValidator::class)
                    ->resolveMinor((string) $validated['custom_selling_price'], $firstVariant, $settings);
            }
        }

        if (! empty($validated['custom_code'])) {
            $code = strtoupper(trim((string) $validated['custom_code']));
            if (TrackingLink::where('code', $code)->exists()) {
                throw ValidationException::withMessages([
                    'custom_code' => ['هذا الكود مستخدم بالفعل، يرجى اختيار كود آخر.'],
                ]);
            }
        } else {
            do {
                $code = strtoupper(Str::random(8));
            } while (TrackingLink::where('code', $code)->exists());
        }

        $tenantId = tenant('id') ? (string) tenant('id') : 'tenant-1';

        TrackingLink::create([
            'tenant_id' => $tenantId,
            'affiliate_id' => $affiliate->id,
            'product_id' => $productId,
            'product_variant_id' => $productVariantId,
            'code' => $code,
            'title' => $validated['title'] ?? ($productId ? "رابط تتبع لمنتج #{$productId}" : 'رابط إحالة المتجر الرئيسي'),
            'custom_selling_price_minor' => $customSellingPriceMinor,
            'commission_rate_percent' => $settings->default_commission_rate,
            'is_active' => true,
        ]);

        return redirect()->route('affiliate.app.links.index')->with('success', 'تم إنشاء رابط التتبع بنجاح.');
    }

    public function destroy(int $id): RedirectResponse
    {
        /** @var Affiliate $affiliate */
        $affiliate = Auth::guard('affiliate')->user();

        /** @var TrackingLink $link */
        $link = TrackingLink::where('affiliate_id', $affiliate->id)->findOrFail($id);
        $link->delete();

        return redirect()->route('affiliate.app.links.index')->with('success', 'تم حذف رابط التتبع بنجاح.');
    }
}
