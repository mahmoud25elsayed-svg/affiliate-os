<?php

declare(strict_types=1);

namespace App\Domain\Affiliate\Services;

use App\Domain\Affiliate\Settings\AffiliateProgramSettings;
use App\Models\Affiliate;
use App\Models\AffiliateClick;
use App\Models\TrackingLink;
use App\Models\WholesalePrice;
use App\Support\Phone\EgyptianPhone;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Lunar\Models\ProductVariant;
use Symfony\Component\HttpFoundation\Cookie;

class AffiliateTrackingService
{
    public const COOKIE_NAME = 'aff_ref';

    public function recordClick(TrackingLink $link, Request $request): ?AffiliateClick
    {
        if (! $link->is_active) {
            return null;
        }

        $appKey = (string) config('app.key');
        $ip = $request->ip() ?? '127.0.0.1';
        $ipHash = hash('sha256', $ip.$appKey);
        $uaHash = hash('sha256', (string) $request->userAgent());

        // Anti-fraud click velocity: max 30 clicks per IP per hour for the same link
        $recentClicksCount = AffiliateClick::query()
            ->where('tracking_link_id', $link->id)
            ->where('ip_hash', $ipHash)
            ->where('created_at', '>=', Carbon::now()->subHour())
            ->count();

        if ($recentClicksCount >= 30) {
            return null;
        }

        /** @var AffiliateClick $click */
        $click = AffiliateClick::create([
            'tenant_id' => $link->tenant_id,
            'tracking_link_id' => $link->id,
            'affiliate_id' => $link->affiliate_id,
            'ip_hash' => $ipHash,
            'ua_hash' => $uaHash,
            'referer' => $request->headers->get('referer') ? substr((string) $request->headers->get('referer'), 0, 500) : null,
            'utm_source' => $request->query('utm_source') ? substr((string) $request->query('utm_source'), 0, 100) : null,
            'utm_medium' => $request->query('utm_medium') ? substr((string) $request->query('utm_medium'), 0, 100) : null,
            'utm_campaign' => $request->query('utm_campaign') ? substr((string) $request->query('utm_campaign'), 0, 100) : null,
            'utm_term' => $request->query('utm_term') ? substr((string) $request->query('utm_term'), 0, 100) : null,
            'utm_content' => $request->query('utm_content') ? substr((string) $request->query('utm_content'), 0, 100) : null,
            'landing_url' => substr((string) $request->fullUrl(), 0, 1000),
        ]);

        $link->increment('clicks_count');

        return $click;
    }

    public function createAttributionCookie(TrackingLink $link): Cookie
    {
        $settings = AffiliateProgramSettings::load();
        $ttlMinutes = max(1, $settings->getAttributionWindowDays() * 24 * 60);

        $payload = $this->generateTokenPayload($link);

        return cookie(
            name: self::COOKIE_NAME,
            value: $payload,
            minutes: $ttlMinutes,
            path: '/',
            domain: null,
            secure: request()->isSecure() || (bool) config('session.secure', false),
            httpOnly: true,
            raw: false,
            sameSite: 'lax'
        );
    }

    public function generateTokenPayload(TrackingLink $link): string
    {
        $appKey = (string) config('app.key');
        $timestamp = Carbon::now()->timestamp;
        $data = "{$link->id}:{$link->affiliate_id}:{$timestamp}";
        $signature = hash_hmac('sha256', $data, $appKey);

        return base64_encode("{$data}:{$signature}");
    }

    /**
     * The tracking link's custom selling price for this variant, when the visitor arrived through
     * a link that carries one for this product. Product page, cart and order creation all use this
     * so the price shown to the customer is the price they are charged.
     *
     * @param  TrackingLink|null  $link  already-resolved link; defaults to the request's attribution cookie
     */
    public function linkedPriceMinor(ProductVariant $variant, ?TrackingLink $link = null): ?int
    {
        if ($link === null) {
            $token = request()->cookie(self::COOKIE_NAME);
            $link = $this->resolveAttributedLink(is_string($token) ? $token : null);
        }

        if ($link === null || $link->custom_selling_price_minor === null) {
            return null;
        }

        $matches = ($link->product_variant_id !== null && (int) $link->product_variant_id === (int) $variant->id)
            || ($link->product_id !== null && (int) $link->product_id === (int) $variant->product_id);

        return $matches ? (int) $link->custom_selling_price_minor : null;
    }

    public function resolveAttributedLink(?string $cookieToken): ?TrackingLink
    {
        if (empty($cookieToken)) {
            return null;
        }

        $decoded = base64_decode($cookieToken, true);
        if ($decoded === false) {
            return null;
        }

        $parts = explode(':', $decoded);
        if (count($parts) !== 4) {
            return null;
        }

        [$linkIdStr, $affiliateIdStr, $timestampStr, $signature] = $parts;
        $linkId = (int) $linkIdStr;
        $affiliateId = (int) $affiliateIdStr;
        $timestamp = (int) $timestampStr;

        $appKey = (string) config('app.key');
        $expectedData = "{$linkId}:{$affiliateId}:{$timestamp}";
        $expectedSignature = hash_hmac('sha256', $expectedData, $appKey);

        if (! hash_equals($expectedSignature, $signature)) {
            return null;
        }

        $settings = AffiliateProgramSettings::load();
        $windowSeconds = $settings->getAttributionWindowDays() * 86400;

        if (Carbon::now()->getTimestamp() - $timestamp > $windowSeconds) {
            return null;
        }

        /** @var TrackingLink|null $link */
        $link = TrackingLink::with(['affiliate', 'variant.wholesalePrice'])->find($linkId);
        if (! $link instanceof TrackingLink || ! $link->is_active) {
            return null;
        }

        // AF-15: Ensure suspended / inactive affiliate cannot be attributed new orders
        if (! $link->affiliate instanceof Affiliate || ! $link->affiliate->isActive()) {
            return null;
        }

        return $link;
    }

    public function isSelfReferral(
        Affiliate $affiliate,
        string $customerPhone,
        ?string $customerEmail = null
    ): bool {
        $settings = AffiliateProgramSettings::load();
        if (! $settings->shouldPreventSelfReferral()) {
            return false;
        }

        $parsedCustomerPhone = EgyptianPhone::tryParse($customerPhone);
        $customerE164 = $parsedCustomerPhone ? $parsedCustomerPhone->e164 : $customerPhone;

        $parsedAffiliatePhone = EgyptianPhone::tryParse((string) $affiliate->phone);
        $affiliateE164 = $parsedAffiliatePhone ? $parsedAffiliatePhone->e164 : (string) $affiliate->phone;

        if ($customerE164 !== '' && $customerE164 === $affiliateE164) {
            return true;
        }

        if (! empty($customerEmail) && ! empty($affiliate->email)) {
            if (strtolower(trim($customerEmail)) === strtolower(trim($affiliate->email))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Calculate affiliate commission for order (AF-10)
     *
     * @param  array<int, array<string, mixed>>  $lineItems
     */
    public function calculateCommission(
        TrackingLink $link,
        int $subtotalMinor,
        ?ProductVariant $variant = null,
        int $quantity = 1,
        array $lineItems = []
    ): int {
        $settings = AffiliateProgramSettings::load();

        if (! empty($lineItems)) {
            $totalCommissionMinor = 0;
            $rate = $link->commission_rate_percent ?? (float) $settings->default_commission_rate;

            foreach ($lineItems as $item) {
                /** @var ProductVariant $itemVariant */
                $itemVariant = $item['variant'];
                $itemQty = max(1, (int) ($item['quantity'] ?? 1));
                $itemSubtotal = (int) ($item['subtotal_minor'] ?? ($item['unit_price_minor'] * $itemQty));

                $isLinked = ($link->product_variant_id !== null && (int) $link->product_variant_id === (int) $itemVariant->id)
                    || ($link->product_id !== null && (int) $link->product_id === (int) $itemVariant->product_id);

                if ($link->custom_selling_price_minor !== null && $isLinked) {
                    $wholesale = $itemVariant->relationLoaded('wholesalePrice') && $itemVariant->getRelation('wholesalePrice') !== null
                        ? $itemVariant->getRelation('wholesalePrice')
                        : WholesalePrice::query()->where('product_variant_id', $itemVariant->id)->first();

                    if ($wholesale instanceof WholesalePrice) {
                        $wholesaleMinor = (int) $wholesale->wholesale_price_minor;
                        $unitMarkupMinor = max(0, $link->custom_selling_price_minor - $wholesaleMinor);
                        $totalCommissionMinor += ($unitMarkupMinor * $itemQty);
                        continue;
                    }
                }

                // Standard rate for non-custom items
                $totalCommissionMinor += (int) round($itemSubtotal * ($rate / 100.0));
            }

            return $settings->clampCommission($totalCommissionMinor);
        }

        if ($link->custom_selling_price_minor !== null && $variant !== null) {
            /** @var WholesalePrice|null $wholesale */
            $wholesale = $variant->relationLoaded('wholesalePrice') && $variant->getRelation('wholesalePrice') !== null
                ? $variant->getRelation('wholesalePrice')
                : WholesalePrice::query()->where('product_variant_id', $variant->id)->first();

            if ($wholesale instanceof WholesalePrice) {
                $wholesaleMinor = (int) $wholesale->wholesale_price_minor;
                $markupMinor = max(0, $link->custom_selling_price_minor - $wholesaleMinor) * max(1, $quantity);

                return $settings->clampCommission($markupMinor);
            }
        }

        $rate = $link->commission_rate_percent ?? (float) $settings->default_commission_rate;
        $calculatedMinor = (int) round($subtotalMinor * ($rate / 100.0));

        return $settings->clampCommission($calculatedMinor);
    }

    public function pruneOldClicks(int $days = 90): int
    {
        $cutoff = Carbon::now()->subDays($days);

        return AffiliateClick::where('created_at', '<', $cutoff)->delete();
    }
}
