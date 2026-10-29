<?php

declare(strict_types=1);

namespace Tests\Feature\Storefront;

use App\Domain\Catalog\Services\ProductService;
use App\Models\Tenant;
use App\Support\Settings\ThemeSettings;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Lunar\FieldTypes\Text;
use Lunar\FieldTypes\TranslatedText;
use Lunar\Models\Collection;
use Lunar\Models\CollectionGroup;
use Tests\TestCase;

class StorefrontPagesTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        app()->setLocale('ar');
        $this->seed(PlanSeeder::class);
        $this->tenant = $this->ensureTenantExists('mystore');
    }

    public function test_storefront_home_renders_with_custom_theme_settings(): void
    {
        $domain = $this->tenant->domains->first()?->domain ?? 'mystore.test';

        // Customize ThemeSettings
        $this->tenant->run(function () {
            $theme = ThemeSettings::load();
            $theme->primary_color = '#10b981';
            $theme->font_family = 'Alexandria';
            $theme->hero_headline = 'أفضل متجر عطور في مصر';
            $theme->save();
        });

        $response = $this->get("http://{$domain}/");

        $response->assertStatus(200);
        $response->assertSee('أفضل متجر عطور في مصر');
        $response->assertSee('#10b981');
        $response->assertSee('Alexandria');
    }

    public function test_category_and_products_listing_pages_render(): void
    {
        $domain = $this->tenant->domains->first()?->domain ?? 'mystore.test';

        $categoryId = $this->tenant->run(function () {
            $group = CollectionGroup::firstOrCreate(['handle' => 'default'], ['name' => 'Default']);
            $cat = Collection::create([
                'collection_group_id' => $group->id,
                'attribute_data' => [
                    'name' => new TranslatedText([
                        'ar' => new Text('ساعات يد رجالي'),
                        'en' => new Text('Men Watches'),
                    ]),
                ],
            ]);

            $service = app(ProductService::class);
            $service->createProduct([
                'name_ar' => 'ساعة كلاسيكية فاخرة',
                'sku' => 'WATCH-001',
                'retail_price' => 45000,
                'wholesale_price' => 30000,
                'min_price' => 35000,
                'max_price' => 50000,
                'collection_ids' => [$cat->id],
            ]);

            return $cat->id;
        });

        // Test category page
        $categoryResponse = $this->get("http://{$domain}/categories/{$categoryId}");
        $categoryResponse->assertStatus(200);
        $categoryResponse->assertSee('ساعات يد رجالي');

        // Test all products page
        $productsResponse = $this->get("http://{$domain}/products");
        $productsResponse->assertStatus(200);
        $productsResponse->assertSee('كافة المنتجات');
    }

    public function test_product_details_renders_with_schema_org_json_ld(): void
    {
        $domain = $this->tenant->domains->first()?->domain ?? 'mystore.test';

        $productId = $this->tenant->run(function () {
            $service = app(ProductService::class);
            $product = $service->createProduct([
                'name_ar' => 'حذاء رياضي مريح',
                'sku' => 'SHOES-SPORT-99',
                'retail_price' => 75000,
                'wholesale_price' => 50000,
                'min_price' => 60000,
                'max_price' => 90000,
                'status' => 'active',
            ]);

            return $product->id;
        });

        $response = $this->get("http://{$domain}/products/{$productId}");

        $response->assertStatus(200);
        $response->assertSee('حذاء رياضي مريح');
        $response->assertSee('SHOES-SPORT-99');
        $response->assertSee('750.00');
        // Schema.org assertions
        $response->assertSee('https://schema.org');
        $response->assertSee('"@type":"Product"', false);
        $response->assertSee('"@type":"Offer"', false);
    }

    public function test_arabic_search_normalizes_text_and_finds_matching_products(): void
    {
        $domain = $this->tenant->domains->first()?->domain ?? 'mystore.test';

        $this->tenant->run(function () {
            $service = app(ProductService::class);
            $service->createProduct([
                'name_ar' => 'أريكة جلدية إيطالية', // Contains Alef with Hamza (أ, إ) and Taa Marbuta (ة)
                'sku' => 'SOFA-ITALIAN-01',
                'retail_price' => 120000,
                'wholesale_price' => 90000,
                'min_price' => 100000,
                'max_price' => 150000,
            ]);
        });

        // Search with normalized letters: "اريكه" instead of "أريكة"
        $response = $this->get("http://{$domain}/search?q=".urlencode('اريكه'));

        $response->assertStatus(200);
        $response->assertSee('أريكة جلدية إيطالية');
    }

    public function test_search_page_renders_without_a_query(): void
    {
        $domain = $this->tenant->domains->first()?->domain ?? 'mystore.test';

        $this->get("http://{$domain}/search")->assertStatus(200);
        $this->get("http://{$domain}/search?q=")->assertStatus(200);
    }

    public function test_static_pages_render_correctly(): void
    {
        $domain = $this->tenant->domains->first()?->domain ?? 'mystore.test';

        $this->get("http://{$domain}/about")->assertStatus(200)->assertSee('عن المتجر');
        $this->get("http://{$domain}/terms")->assertStatus(200)->assertSee('الشروط والأحكام');
        $this->get("http://{$domain}/privacy")->assertStatus(200)->assertSee('سياسة الخصوصية');
        $this->get("http://{$domain}/shipping")->assertStatus(200)->assertSee('الشحن والاسترجاع');
    }
}
