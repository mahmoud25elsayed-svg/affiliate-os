<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Domain\Storefront\Services\StorefrontCacheService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Response;
use Lunar\Models\Collection;
use Lunar\Models\Product;

class SitemapController extends Controller
{
    public function __invoke(): Response
    {
        $xml = StorefrontCacheService::remember('sitemap', 86400, function () {
            $urls = [
                ['loc' => url('/'), 'priority' => '1.0', 'changefreq' => 'daily'],
                ['loc' => url('/about'), 'priority' => '0.5', 'changefreq' => 'monthly'],
                ['loc' => url('/terms'), 'priority' => '0.3', 'changefreq' => 'monthly'],
                ['loc' => url('/privacy'), 'priority' => '0.3', 'changefreq' => 'monthly'],
                ['loc' => url('/shipping'), 'priority' => '0.5', 'changefreq' => 'monthly'],
            ];

            $categories = Collection::all();
            foreach ($categories as $cat) {
                /** @var Collection $cat */
                $urls[] = [
                    'loc' => url('/categories/'.$cat->id),
                    'priority' => '0.8',
                    'changefreq' => 'weekly',
                    'lastmod' => $cat->updated_at ? $cat->updated_at->toAtomString() : now()->toAtomString(),
                ];
            }

            $products = Product::where('status', 'published')->get();
            foreach ($products as $prod) {
                /** @var Product $prod */
                $urls[] = [
                    'loc' => url('/products/'.$prod->id),
                    'priority' => '0.9',
                    'changefreq' => 'daily',
                    'lastmod' => $prod->updated_at ? $prod->updated_at->toAtomString() : now()->toAtomString(),
                ];
            }

            $content = '<?xml version="1.0" encoding="UTF-8"?>'.PHP_EOL;
            $content .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'.PHP_EOL;

            foreach ($urls as $url) {
                $content .= '  <url>'.PHP_EOL;
                $content .= '    <loc>'.htmlspecialchars($url['loc']).'</loc>'.PHP_EOL;
                if (! empty($url['lastmod'])) {
                    $content .= '    <lastmod>'.$url['lastmod'].'</lastmod>'.PHP_EOL;
                }
                $content .= '    <changefreq>'.$url['changefreq'].'</changefreq>'.PHP_EOL;
                $content .= '    <priority>'.$url['priority'].'</priority>'.PHP_EOL;
                $content .= '  </url>'.PHP_EOL;
            }

            $content .= '</urlset>';

            return $content;
        });

        return response($xml, 200, ['Content-Type' => 'text/xml']);
    }
}
