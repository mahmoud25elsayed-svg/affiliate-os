<?php

declare(strict_types=1);

namespace App\Domain\Analytics\Services;

use App\Models\PlatformVisit;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PlatformVisitorAnalyticsService
{
    /**
     * Record a privacy-friendly platform visit (no cookies, daily rotating salted hash).
     */
    public function recordVisit(Request $request): PlatformVisit
    {
        $path = '/'.ltrim($request->path(), '/');
        $rawReferer = (string) $request->header('referer', '');
        $referrerDomain = null;
        $referrerType = 'direct';

        if ($rawReferer !== '') {
            $parsedUrl = parse_url($rawReferer);
            $referrerDomain = is_array($parsedUrl) ? ($parsedUrl['host'] ?? null) : null;

            if ($referrerDomain) {
                $referrerType = $this->classifyReferrer($referrerDomain);
            }
        }

        if ($request->has('ref') || $request->input('utm_medium') === 'affiliate') {
            $referrerType = 'affiliate';
        }

        $userAgent = (string) $request->userAgent();
        $deviceCategory = $this->classifyDevice($userAgent);

        $visitorHash = $this->generatePrivacyHash($request);

        return PlatformVisit::create([
            'path' => substr($path, 0, 255),
            'referrer_domain' => $referrerDomain ? substr($referrerDomain, 0, 255) : null,
            'referrer_type' => $referrerType,
            'device_category' => $deviceCategory,
            'country_code' => 'EG',
            'visitor_hash' => $visitorHash,
            'converted' => false,
            'visited_at' => now(),
        ]);
    }

    /**
     * Mark a visitor as converted when completing an action (e.g. merchant registered, contact submitted).
     */
    public function recordConversion(Request $request, string $conversionType = 'merchant_registered'): int
    {
        $visitorHash = $this->generatePrivacyHash($request);

        return PlatformVisit::where('visitor_hash', $visitorHash)
            ->where('visited_at', '>=', now()->startOfDay())
            ->where('converted', false)
            ->update([
                'converted' => true,
                'conversion_type' => $conversionType,
            ]);
    }

    /**
     * Generate an aggregated summary of visitor metrics and conversion rates.
     *
     * @return array<string, mixed>
     */
    public function getSummary(int $days = 30): array
    {
        $cutoff = now()->subDays($days)->startOfDay();

        $totalViews = PlatformVisit::where('visited_at', '>=', $cutoff)->count();
        $uniqueVisitors = PlatformVisit::where('visited_at', '>=', $cutoff)
            ->distinct('visitor_hash')
            ->count('visitor_hash');

        $conversionsCount = PlatformVisit::where('visited_at', '>=', $cutoff)
            ->where('converted', true)
            ->distinct('visitor_hash')
            ->count('visitor_hash');

        $conversionRate = ($uniqueVisitors > 0)
            ? round(($conversionsCount / $uniqueVisitors) * 100, 2)
            : 0.0;

        /** @var Collection<int, object{referrer_type: string, count: int}> $referrerStats */
        $referrerStats = PlatformVisit::where('visited_at', '>=', $cutoff)
            ->select('referrer_type', DB::raw('count(*) as count'))
            ->groupBy('referrer_type')
            ->orderByDesc('count')
            ->get();

        /** @var Collection<int, object{device_category: string, count: int}> $deviceStats */
        $deviceStats = PlatformVisit::where('visited_at', '>=', $cutoff)
            ->select('device_category', DB::raw('count(*) as count'))
            ->groupBy('device_category')
            ->orderByDesc('count')
            ->get();

        /** @var Collection<int, object{path: string, count: int}> $topPages */
        $topPages = PlatformVisit::where('visited_at', '>=', $cutoff)
            ->select('path', DB::raw('count(*) as count'))
            ->groupBy('path')
            ->orderByDesc('count')
            ->limit(10)
            ->get();

        return [
            'period_days' => $days,
            'total_views' => $totalViews,
            'unique_visitors' => $uniqueVisitors,
            'conversions_count' => $conversionsCount,
            'conversion_rate_percentage' => $conversionRate,
            'referrers' => $referrerStats->pluck('count', 'referrer_type')->all(),
            'devices' => $deviceStats->pluck('count', 'device_category')->all(),
            'top_pages' => $topPages->map(fn ($p) => ['path' => $p->path, 'views' => $p->count])->all(),
        ];
    }

    /**
     * Generate privacy-friendly daily rotating salt hash.
     */
    protected function generatePrivacyHash(Request $request): string
    {
        $salt = (string) config('app.key').now()->format('Y-m-d');
        $ip = $request->ip() ?? '127.0.0.1';
        $ua = (string) $request->userAgent();

        return hash('sha256', "{$salt}:{$ip}:{$ua}");
    }

    /**
     * Classify referrer domain into high-level channel.
     */
    protected function classifyReferrer(string $domain): string
    {
        $domain = strtolower($domain);

        $searchEngines = ['google.', 'bing.', 'yahoo.', 'duckduckgo.', 'yandex.', 'ecosia.'];
        foreach ($searchEngines as $engine) {
            if (str_contains($domain, $engine)) {
                return 'search';
            }
        }

        $socialNetworks = ['facebook.', 'instagram.', 't.co', 'twitter.', 'tiktok.', 'linkedin.', 'youtube.', 'pinterest.'];
        foreach ($socialNetworks as $social) {
            if (str_contains($domain, $social)) {
                return 'social';
            }
        }

        $appHost = (string) parse_url((string) config('app.url'), PHP_URL_HOST);
        if ($appHost !== '' && str_contains($domain, $appHost)) {
            return 'internal';
        }

        return 'referral';
    }

    /**
     * Classify user agent into device category.
     */
    protected function classifyDevice(string $ua): string
    {
        $ua = strtolower($ua);

        if (str_contains($ua, 'ipad') || str_contains($ua, 'tablet') || str_contains($ua, 'playbook')) {
            return 'tablet';
        }

        if (str_contains($ua, 'mobile') || str_contains($ua, 'android') || str_contains($ua, 'iphone') || str_contains($ua, 'ipod')) {
            return 'mobile';
        }

        return 'desktop';
    }
}
