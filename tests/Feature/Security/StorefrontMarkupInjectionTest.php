<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Domain\Catalog\Services\ProductService;
use App\Models\Tenant;
use App\Support\Settings\ThemeSettings;
use Database\Seeders\PlanSeeder;
use Lunar\Models\Product;
use Tests\TestCase;

class StorefrontMarkupInjectionTest extends TestCase
{
    protected Tenant $tenant;

    protected string $domain;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PlanSeeder::class);
        $this->tenant = $this->ensureTenantExists('xssstore');
        $this->domain = $this->tenant->domains->first()?->domain ?? 'xssstore.localhost';
    }

    public function test_product_name_cannot_break_out_of_json_ld_script(): void
    {
        $productId = $this->tenant->run(function () {
            app(ProductService::class)->createProduct([
                'name_ar' => 'Bad</script><script>alert(1)</script>',
                'sku' => 'XSS-01',
                'retail_price' => 10000,
                'wholesale_price' => 7000,
                'min_price' => 8000,
                'max_price' => 12000,
            ]);

            return (int) Product::query()->latest('id')->value('id');
        });

        $html = $this->get("http://{$this->domain}/products/{$productId}")->assertStatus(200)->getContent();

        $this->assertStringNotContainsString('</script><script>alert(1)', (string) $html);
        $this->assertStringContainsString('</script>', (string) $html);
    }

    public function test_custom_css_cannot_close_the_style_tag(): void
    {
        $this->tenant->run(function () {
            $theme = ThemeSettings::load();
            $theme->custom_css = '.x{color:red}</style><script>alert(1)</script>';
            $theme->save();
        });

        $html = (string) $this->get("http://{$this->domain}/")->assertStatus(200)->getContent();

        $this->assertStringContainsString('.x{color:red}', $html);
        $this->assertStringNotContainsString('<script>alert(1)', $html);
    }
}
