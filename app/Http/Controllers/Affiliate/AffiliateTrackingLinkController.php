<?php

declare(strict_types=1);

namespace App\Http\Controllers\Affiliate;

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
            'custom_selling_price' => TrackingLinkPriceValidator::INPUT_RULE,
            'custom_code' => 'nullable|string|alpha_dash|max:32',
        ], [
            'custom_code.alpha_dash' => __('كود الرابط المخصص يجب أن يحتوي على أحرف وأرقام وشرطات فقط.'),
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
                // AF-20: integer minor math, never floats
                $customSellingPriceMinor = app(TrackingLinkPriceValidator::class)
                    ->resolveMinor((string) $validated['custom_selling_price'], $firstVariant, $settings);
            }
        }

        // Generate or check code
        if (! empty($validated['custom_code'])) {
            $code = strtoupper(trim((string) $validated['custom_code']));
            $exists = TrackingLink::where('code', $code)->exists();
            if ($exists) {
                throw ValidationException::withMessages([
                    'custom_code' => [__('هذا الكود مستخدم بالفعل، يرجى اختيار كود آخر.')],
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
            'title' => $validated['title'] ?? ($productId ? __('رابط تتبع لمنتج #:product_id', ['product_id' => $productId]) : __('رابط إحالة المتجر الرئيسي')),
            'custom_selling_price_minor' => $customSellingPriceMinor,
            'commission_rate_percent' => $settings->default_commission_rate,
            'is_active' => true,
        ]);

        return redirect()->route('affiliate.links.index')->with('success', __('تم إنشاء رابط التتبع بنجاح.'));
    }

    public function destroy(int $id): RedirectResponse
    {
        /** @var Affiliate $affiliate */
        $affiliate = Auth::guard('affiliate')->user();

        /** @var TrackingLink $link */
        $link = TrackingLink::where('affiliate_id', $affiliate->id)->findOrFail($id);
        $link->delete();

        return redirect()->route('affiliate.links.index')->with('success', __('تم حذف رابط التتبع بنجاح.'));
    }
}
