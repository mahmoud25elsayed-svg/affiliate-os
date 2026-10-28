<?php

declare(strict_types=1);

namespace App\Http\Controllers\Affiliate;

use App\Domain\Affiliate\Settings\AffiliateProgramSettings;
use App\Http\Controllers\Controller;
use App\Models\Affiliate;
use App\Models\TrackingLink;
use App\Models\WholesalePrice;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Lunar\Models\Product;
use Lunar\Models\ProductVariant;

class AffiliateTrackingLinkController extends Controller
{
    public function index(Request $request): View
    {
        /** @var Affiliate $affiliate */
        $affiliate = Auth::guard('affiliate')->user();

        $links = TrackingLink::query()
            ->where('affiliate_id', $affiliate->id)
            ->with(['product', 'variant'])
            ->latest()
            ->paginate(15);

        return view('affiliate.links.index', [
            'links' => $links,
            'affiliate' => $affiliate,
        ]);
    }

    public function create(Request $request): View
    {
        /** @var Affiliate $affiliate */
        $affiliate = Auth::guard('affiliate')->user();

        $products = Product::query()
            ->with(['variants.wholesalePrice'])
            ->get();

        $selectedProductId = $request->input('product_id') ? (int) $request->input('product_id') : null;
        $settings = AffiliateProgramSettings::load();

        return view('affiliate.links.create', [
            'products' => $products,
            'selectedProductId' => $selectedProductId,
            'settings' => $settings,
            'affiliate' => $affiliate,
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
            'custom_selling_price' => 'nullable|numeric|min:1',
            'custom_code' => 'nullable|string|alpha_dash|max:32',
        ], [
            'custom_code.alpha_dash' => 'كود الرابط المخصص يجب أن يحتوي على أحرف وأرقام وشرطات فقط.',
        ]);

        $productId = ! empty($validated['product_id']) ? (int) $validated['product_id'] : null;
        $productVariantId = null;
        $customSellingPriceMinor = null;

        if ($productId !== null) {
            /** @var Product $product */
            $product = Product::with(['variants.wholesalePrice'])->findOrFail($productId);
            /** @var ProductVariant|null $firstVariant */
            $firstVariant = $product->variants->first();
            if ($firstVariant !== null) {
                $productVariantId = $firstVariant->id;
            }

            if (! empty($validated['custom_selling_price'])) {
                $sellingPriceMinor = (int) round(((float) $validated['custom_selling_price']) * 100);

                /** @var WholesalePrice|null $wholesalePrice */
                $wholesalePrice = $firstVariant?->relationLoaded('wholesalePrice')
                    ? $firstVariant->getRelation('wholesalePrice')
                    : WholesalePrice::query()->where('product_variant_id', $firstVariant?->id)->first();

                if (! $wholesalePrice instanceof WholesalePrice) {
                    throw ValidationException::withMessages([
                        'product_id' => ['هذا المنتج لا يحتوي على سعر جملة معتمد حالياً.'],
                    ]);
                }

                $minAllowed = $wholesalePrice->min_selling_price_minor ?? ($wholesalePrice->wholesale_price_minor + $settings->min_selling_markup_minor);
                $maxAllowed = $wholesalePrice->max_selling_price_minor ?? ($wholesalePrice->wholesale_price_minor + $settings->max_selling_markup_minor);

                if ($sellingPriceMinor < $minAllowed || $sellingPriceMinor > $maxAllowed) {
                    $minFormatted = number_format($minAllowed / 100, 2);
                    $maxFormatted = number_format($maxAllowed / 100, 2);

                    throw ValidationException::withMessages([
                        'custom_selling_price' => ["سعر البيع يجب أن يكون بين {$minFormatted} و {$maxFormatted} ج.م"],
                    ]);
                }

                $customSellingPriceMinor = $sellingPriceMinor;
            }
        }

        // Generate or check code
        if (! empty($validated['custom_code'])) {
            $code = strtoupper(trim((string) $validated['custom_code']));
            $exists = TrackingLink::where('code', $code)->exists();
            if ($exists) {
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

        return redirect()->route('affiliate.links.index')->with('success', 'تم إنشاء رابط التتبع بنجاح.');
    }

    public function destroy(int $id): RedirectResponse
    {
        /** @var Affiliate $affiliate */
        $affiliate = Auth::guard('affiliate')->user();

        /** @var TrackingLink $link */
        $link = TrackingLink::where('affiliate_id', $affiliate->id)->findOrFail($id);
        $link->delete();

        return redirect()->route('affiliate.links.index')->with('success', 'تم حذف رابط التتبع بنجاح.');
    }
}
