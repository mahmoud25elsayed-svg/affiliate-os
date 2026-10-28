<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Domain\Affiliate\Services\AffiliateTrackingService;
use App\Http\Controllers\Controller;
use App\Models\TrackingLink;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AffiliateReferralController extends Controller
{
    public function __invoke(
        string $code,
        Request $request,
        AffiliateTrackingService $trackingService
    ): RedirectResponse {
        /** @var TrackingLink $link */
        $link = TrackingLink::where('code', $code)
            ->where('is_active', true)
            ->firstOrFail();

        // 1. Record the click with anti-fraud throttling
        $trackingService->recordClick($link, $request);

        // 2. Generate attribution cookie with configured window
        $cookie = $trackingService->createAttributionCookie($link);

        // 3. Determine redirect target (Product or Home)
        $destination = $link->product_id !== null
            ? route('storefront.products.show', ['id' => $link->product_id])
            : route('storefront.home');

        // Forward query string parameters if present
        $queryString = $request->getQueryString();
        if ($queryString !== null && $queryString !== '') {
            $separator = str_contains($destination, '?') ? '&' : '?';
            $destination .= $separator.$queryString;
        }

        return redirect()->to($destination)->withCookie($cookie);
    }
}
