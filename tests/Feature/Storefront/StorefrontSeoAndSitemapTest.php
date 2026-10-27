<?php

declare(strict_types=1);

namespace Tests\Feature\Storefront;

use App\Domain\Catalog\Services\ProductService;
use App\Models\Tenant;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Lunar\FieldTypes\Text;
use Lunar\FieldTypes\TranslatedText;
use Lunar\Models\Collection;
use Lunar\Models\CollectionGroup;
use Tests\TestCase;

class StorefrontSeoAndSitemapTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PlanSeeder::class);
        $this->tenant = $this->ensureTenantExists('seostore');
    }

    public function test_sitemap_xml_generates_valid_xml_with_all_sections(): void
    {
        $domain = $this->tenant->domains->first()?->domain ?? 'seostore.test';

        $this->tenant->run(function () {
            $group = CollectionGroup::firstOrCreate(['handle' => 'default'], ['name' => 'Default']);
            $cat = Collection::create([
                'collection_group_id' => $group->id,
                'attribute_data' => [
                    'name' => new TranslatedText([
                        'ar' => new Text('إلكترونيات'),
                        'en' => new Text('Electronics'),
                    ]),
                ],
            ]);

            $service = app(ProductService::class);
            $service->createProduct([
                'name_ar' => 'هاتف ذكي 5G',
                'sku' => 'PHONE-5G',
                'retail_price' => 99000,
                'wholesale_price' => 70000,
                'min_price' => 80000,
                'max_price' => 110000,
                'collection_ids' => [$cat->id],
            ]);
        });

        $response = $this->get("http://{$domain}/sitemap.xml");

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/xml; charset=UTF-8');
        $response->assertSee('<urlset', false);
        $response->assertSee('/about</loc>', false);
        $response->assertSee('/categories/', false);
        $response->assertSee('/products/', false);
    }

    public function test_robots_txt_specifies_sitemap_and_public_directives(): void
    {
        $domain = $this->tenant->domains->first()?->domain ?? 'seostore.test';

        $response = $this->get("http://{$domain}/robots.txt");

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/plain; charset=UTF-8');
        $response->assertSee('User-agent: *');
        $response->assertSee('Allow: /');
        $response->assertSee('Disallow: /admin/');
        $response->assertSee("Sitemap: http://{$domain}/sitemap.xml");
    }

    public function test_seo_meta_tags_and_hreflang_are_rendered(): void
    {
        $domain = $this->tenant->domains->first()?->domain ?? 'seostore.test';

        $response = $this->get("http://{$domain}/");

        $response->assertStatus(200);
        $response->assertSee('rel="canonical"', false);
        $response->assertSee('hreflang="ar"', false);
        $response->assertSee('hreflang="en"', false);
        $response->assertSee('og:title', false);
        $response->assertSee('og:description', false);
    }
}
