<?php

declare(strict_types=1);

namespace App\Http\Controllers\Affiliate;

use App\Http\Controllers\Controller;
use App\Models\Affiliate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AffiliatePixelController extends Controller
{
    public function show(): View
    {
        /** @var Affiliate $affiliate */
        $affiliate = Auth::guard('affiliate')->user();

        return view('affiliate.pixels.show', [
            'affiliate' => $affiliate,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        /** @var Affiliate $affiliate */
        $affiliate = Auth::guard('affiliate')->user();

        $validated = $request->validate([
            'meta_pixel_id' => ['nullable', 'string', 'max:50'],
            'tiktok_pixel_id' => ['nullable', 'string', 'max:50'],
        ]);

        $affiliate->update([
            'meta_pixel_id' => $validated['meta_pixel_id'] ? trim($validated['meta_pixel_id']) : null,
            'tiktok_pixel_id' => $validated['tiktok_pixel_id'] ? trim($validated['tiktok_pixel_id']) : null,
        ]);

        return redirect()->route('affiliate.pixels.show')
            ->with('success', __('تم حفظ بكسلات التتبع الإعلاني بنجاح.'));
    }
}
