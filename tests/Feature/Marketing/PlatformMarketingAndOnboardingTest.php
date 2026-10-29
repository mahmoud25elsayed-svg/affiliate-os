<?php

declare(strict_types=1);

namespace Tests\Feature\Marketing;

use App\Domain\Analytics\Services\PlatformVisitorAnalyticsService;
use App\Domain\Onboarding\Services\MerchantOnboardingProgressService;
use App\Domain\Onboarding\Services\TenantDemoDataService;
use App\Models\Affiliate;
use App\Models\PlatformVisit;
use App\Models\Tenant;
use App\Models\TenantShippingRate;
use App\Models\User;
use App\Support\Tenancy\Permissions\TenantPermissionMatrix;
use Database\Seeders\EgyptGovernoratesSeeder;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\RateLimiter;
use Lunar\FieldTypes\Text;
use Lunar\Models\Language;
use Lunar\Models\Product;
use Lunar\Models\ProductType;
use Lunar\Models\ProductVariant;
use Lunar\Models\TaxClass;
use Tests\TestCase;

class PlatformMarketingAndOnboardingTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected User $merchant;

    protected string $tenantDomain = 'onboarding-store.localhost';

    protected function setUp(): void
    {
        parent::setUp();

        RateLimiter::clear('contact_submit:127.0.0.1');

        $this->seed(PlanSeeder::class);
        $this->seed(EgyptGovernoratesSeeder::class);

        Language::firstOrCreate(['code' => 'ar'], ['name' => 'Arabic', 'default' => true]);

        $this->tenant = Tenant::create([
            'id' => 'onboarding-store',
            'name' => 'Onboarding Test Store',
            'status' => 'active',
        ]);

        $this->tenant->domains()->create(['domain' => $this->tenantDomain]);

        $this->merchant = User::create([
            'name' => 'Merchant Test',
            'email' => 'merchant@onboarding-store.test',
            'password' => bcrypt('password123'),
            'tenant_id' => $this->tenant->id,
        ]);

        TenantPermissionMatrix::seedForTenant($this->tenant->id);
        TenantPermissionMatrix::assignRole($this->merchant, TenantPermissionMatrix::ROLE_OWNER, $this->tenant->id);
    }

    public function test_platform_home_page_renders_with_schema_and_plans(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertSee('AffiliateOS');
        $response->assertSee('SoftwareApplication');
        $response->assertSee('البداية');
        $response->assertSee('المحترف');
        $response->assertSee('المؤسسات');
        $response->assertSee('أنشئ متجرك الآن');
    }

    public function test_platform_features_page_renders_successfully(): void
    {
        $response = $this->get('/features');

        $response->assertStatus(200);
        $response->assertSee('الميزات والحلول');
        $response->assertSee('محرك Lunar فائق السرعة');
        $response->assertSee('إسناد النقرة الأخيرة وبوابة الأفلييت');
        $response->assertSee('دفتر أستاذ محاسبي مزدوج');
        $response->assertSee('إدارة الكول سنتر والربط اللوجستي');
    }

    public function test_platform_pricing_page_renders_dynamic_plans_and_comparison_table(): void
    {
        $response = $this->get('/pricing');

        $response->assertStatus(200);
        $response->assertSee('خطط الأسعار ومقارنة الباقات');
        $response->assertSee('الدفع سنوياً');
        $response->assertSee('خصم 20%');
        $response->assertSee('المقارنة الكاملة');
        $response->assertSee('دفتر الأستاذ المحاسبي المزدوج');
        $response->assertSee('غير محدود');
    }

    public function test_platform_legal_pages_render_successfully(): void
    {
        $pages = ['/terms', '/privacy', '/dpa', '/affiliate-terms'];

        foreach ($pages as $url) {
            $response = $this->get($url);
            $response->assertStatus(200);
            $response->assertSee('AffiliateOS');
            $response->assertSee('العودة للصفحة الرئيسية');
        }
    }

    public function test_platform_sitemap_renders_valid_xml(): void
    {
        $response = $this->get('/sitemap.xml');

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/xml');
        $response->assertSee('<urlset', false);
        $response->assertSee('/features', false);
        $response->assertSee('/pricing', false);
        $response->assertSee('/register/merchant', false);
    }

    public function test_login_shortcut_redirects_to_platform_login(): void
    {
        $response = $this->get('/login');

        $response->assertRedirect('/platform/login');
    }

    public function test_health_endpoint_masks_internal_details_for_public_requests(): void
    {
        $publicResponse = $this->get('/health');
        $publicResponse->assertStatus(200);
        $publicResponse->assertJsonStructure([
            'status',
            'timestamp',
            'environment',
            'checks' => [
                'database' => ['status'],
                'queue' => ['status'],
                'scheduler' => ['status', 'last_run'],
                'storage' => ['status'],
                'platform' => ['status'],
            ],
        ]);
        // Raw internal disk byte counts and pending jobs must not be leaked publicly
        $publicData = $publicResponse->json();
        $this->assertArrayNotHasKey('free_bytes', $publicData['checks']['storage']);
        $this->assertArrayNotHasKey('pending_jobs', $publicData['checks']['queue']);

        // Authenticated with token reveals detailed diagnostics
        config(['services.health.token' => 'test-health-token']);
        $authResponse = $this->withHeader('X-Health-Token', 'test-health-token')->get('/health');
        $authResponse->assertStatus(200);
        $authData = $authResponse->json();
        $this->assertArrayHasKey('free_bytes', $authData['checks']['storage']);
        $this->assertArrayHasKey('pending_jobs', $authData['checks']['queue']);

        // Query-string tokens leak into access logs and are not accepted
        $queryData = $this->withHeaders(['X-Health-Token' => ''])->get('/health?token=test-health-token')->json();
        $this->assertArrayNotHasKey('pending_jobs', $queryData['checks']['queue']);

        // An unset server token never authorizes, even when the caller sends an empty value
        config(['services.health.token' => '']);
        $emptyData = $this->withHeader('X-Health-Token', '')->get('/health')->json();
        $this->assertArrayNotHasKey('pending_jobs', $emptyData['checks']['queue']);
    }

    public function test_platform_faq_page_renders_with_faq_page_schema(): void
    {
        $response = $this->get('/faq');

        $response->assertStatus(200);
        $response->assertSee('الأسئلة الشائعة');
        $response->assertSee('FAQPage');
        $response->assertSee('كيف أبدأ مع المنصة وكم تستغرق تهيئة المتجر؟');
        $response->assertSee('هل يمكنني ربط اسم نطاق مخصص');
    }

    public function test_platform_blog_listing_and_article_detail(): void
    {
        // 1. Blog index
        $indexResponse = $this->get('/blog');
        $indexResponse->assertStatus(200);
        $indexResponse->assertSee('مدونة التجارة والتسويق بالعمولة');
        $indexResponse->assertSee('الدليل الشامل للتوسع في تجارة الدفع عند الاستلام');

        // 2. Blog post
        $postResponse = $this->get('/blog/cod-ecommerce-egypt-scaling-guide');
        $postResponse->assertStatus(200);
        $postResponse->assertSee('BlogPosting');
        $postResponse->assertSee('الدليل الشامل للتوسع في تجارة الدفع عند الاستلام (COD) في مصر');
        $postResponse->assertSee('فريق استشارات AffiliateOS');

        // 3. Invalid blog post
        $invalidResponse = $this->get('/blog/non-existing-article-slug');
        $invalidResponse->assertStatus(404);
    }

    public function test_platform_contact_page_and_inquiry_submission(): void
    {
        $contactResponse = $this->get('/contact');
        $contactResponse->assertStatus(200);
        $contactResponse->assertSee('تواصل معنا واستشر خبراء المنصة');

        $submitResponse = $this->post('/contact', [
            'name' => 'أحمد طارق',
            'email' => 'ahmed.tarek@example.test',
            'phone' => '01012345678',
            'business_type' => 'fashion',
            'message' => 'أرغب في الاستفسار عن إمكانية نقل متجري الحالي وربط 50 مسوقاً.',
        ]);

        $submitResponse->assertRedirect(route('platform.marketing.contact'));
        $submitResponse->assertSessionHas('success');

        $this->assertDatabaseHas('platform_visits', [
            'conversion_type' => 'contact_submitted',
            'converted' => true,
        ]);
    }

    public function test_contact_form_rate_limiting(): void
    {
        $payload = [
            'name' => 'أحمد طارق',
            'email' => 'ahmed.tarek@example.test',
            'phone' => '01012345678',
            'message' => 'رسالة استفسار عادية لاختبار التقييد الزمني.',
        ];

        // 5 successful requests
        for ($i = 0; $i < 5; $i++) {
            $this->post('/contact', $payload);
        }

        // 6th attempt should trigger rate limiter exception
        $response = $this->post('/contact', $payload);
        $response->assertSessionHasErrors(['email']);
    }

    public function test_privacy_friendly_analytics_records_daily_salted_hash_without_cookies(): void
    {
        $response = $this->get('/');
        $response->assertStatus(200);

        // Verify no invasive cookies set
        $cookies = $response->headers->getCookies();
        $cookieNames = array_map(fn ($c) => $c->getName(), $cookies);
        $this->assertNotContains('visitor_id', $cookieNames);
        $this->assertNotContains('tracking_id', $cookieNames);

        // Verify visit record exists with SHA-256 hash (64 hex characters)
        /** @var PlatformVisit|null $visit */
        $visit = PlatformVisit::latest('id')->first();
        $this->assertNotNull($visit);
        $this->assertEquals(64, strlen($visit->visitor_hash));
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $visit->visitor_hash);
    }

    public function test_visitor_hash_rotates_across_different_salts(): void
    {
        $analytics = app(PlatformVisitorAnalyticsService::class);

        $reflection = new \ReflectionClass($analytics);
        $method = $reflection->getMethod('generatePrivacyHash');
        $method->setAccessible(true);

        $request = Request::create('/', 'GET', [], [], [], [
            'REMOTE_ADDR' => '192.168.1.10',
            'HTTP_USER_AGENT' => 'Mozilla/5.0 Chrome/120.0',
        ]);

        /** @var string $hashDay1 */
        $hashDay1 = $method->invoke($analytics, $request);
        $this->assertEquals(64, strlen($hashDay1));

        // When APP_KEY changes or salt changes, hash must differ
        $oldKey = config('app.key');
        config(['app.key' => 'base64:AnotherDifferentKeyForTestingSalt123456=']);

        /** @var string $hashDifferentSalt */
        $hashDifferentSalt = $method->invoke($analytics, $request);
        $this->assertNotEquals($hashDay1, $hashDifferentSalt);

        config(['app.key' => $oldKey]);
    }

    public function test_analytics_aggregates_referrers_devices_and_conversions(): void
    {
        /** @var PlatformVisitorAnalyticsService $analytics */
        $analytics = app(PlatformVisitorAnalyticsService::class);

        // 1. Google Search from Mobile
        $this->withHeaders([
            'User-Agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 Mobile/15E148 Safari/604.1',
            'Referer' => 'https://www.google.com.eg/search?q=affiliate+os',
        ])->get('/features');

        // 2. Facebook from Desktop
        $this->withHeaders([
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/120.0.0.0 Safari/537.36',
            'Referer' => 'https://l.facebook.com/l.php?u=https://affiliate-os.com',
        ])->get('/pricing');

        // 3. Direct Visit
        $this->withHeaders([
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/120.0.0.0 Safari/537.36',
        ])->get('/');

        $summary = $analytics->getSummary(30);

        $this->assertGreaterThanOrEqual(3, $summary['total_views']);
        $this->assertGreaterThanOrEqual(2, $summary['unique_visitors']);
        $this->assertArrayHasKey('devices', $summary);
        $this->assertArrayHasKey('referrers', $summary);
        $this->assertGreaterThanOrEqual(1, $summary['referrers']['search'] ?? 0);
        $this->assertGreaterThanOrEqual(1, $summary['referrers']['social'] ?? 0);
    }

    public function test_merchant_onboarding_progress_service_evaluates_steps(): void
    {
        /** @var MerchantOnboardingProgressService $progressService */
        $progressService = app(MerchantOnboardingProgressService::class);

        // Initial state for tenant
        $initialProgress = $progressService->getProgress($this->tenant);
        $this->assertIsArray($initialProgress);
        $this->assertArrayHasKey('completion_percentage', $initialProgress);
        $this->assertArrayHasKey('steps', $initialProgress);
        $this->assertFalse($initialProgress['steps']['first_product']['completed']);

        // Create a product for tenant
        $this->tenant->run(function () {
            $productType = ProductType::firstOrCreate(['name' => 'Standard']);
            $taxClass = TaxClass::firstOrCreate(['name' => 'Standard']);

            $product = Product::create([
                'product_type_id' => $productType->id,
                'status' => 'published',
                'attribute_data' => ['name' => new Text('منتج حقيقي')],
            ]);

            ProductVariant::create([
                'product_id' => $product->id,
                'sku' => 'REAL-PROD-01',
                'tax_class_id' => $taxClass->id,
                'stock' => 50,
            ]);
        });

        // Add shipping rate for tenant
        TenantShippingRate::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'شحن القاهرة والجيزة',
            'governorate_id' => 1,
            'base_rate_minor' => 4500,
            'is_active' => true,
        ]);

        $updatedProgress = $progressService->getProgress($this->tenant);
        $this->assertTrue($updatedProgress['steps']['first_product']['completed']);
        $this->assertTrue($updatedProgress['steps']['shipping_setup']['completed']);
        $this->assertGreaterThan($initialProgress['completion_percentage'], $updatedProgress['completion_percentage']);
    }

    public function test_store_onboarding_progress_endpoint_requires_auth_and_returns_json(): void
    {
        // 1. Unauthenticated request -> 401 Unauthorized
        $unauthResponse = $this->getJson("http://{$this->tenantDomain}/store/onboarding/progress");
        $unauthResponse->assertStatus(401);

        // 2. Authenticated merchant request -> returns 200 JSON
        $authResponse = $this->actingAs($this->merchant)
            ->get("http://{$this->tenantDomain}/store/onboarding/progress");

        $authResponse->assertStatus(200);
        $authResponse->assertJsonStructure([
            'completion_percentage',
            'completed_steps_count',
            'total_steps_count',
            'is_completed',
            'has_demo_data',
            'steps' => [
                'store_logo',
                'first_product',
                'shipping_setup',
                'payment_gateways',
                'affiliate_program',
            ],
        ]);
    }

    public function test_seed_demo_data_service_creates_products_affiliates_and_orders(): void
    {
        /** @var TenantDemoDataService $demoService */
        $demoService = app(TenantDemoDataService::class);

        $this->assertFalse($demoService->hasDemoData($this->tenant));

        $seedResult = $demoService->seedDemoData($this->tenant);

        $this->assertEquals(5, $seedResult['products']);
        $this->assertEquals(2, $seedResult['affiliates']);
        $this->assertEquals(3, $seedResult['orders']);
        $this->assertEquals(1, $seedResult['customers']);

        $this->assertTrue($demoService->hasDemoData($this->tenant));

        // Verify SKU prefix
        $this->assertDatabaseHas('lunar_product_variants', [
            'tenant_id' => $this->tenant->id,
            'sku' => 'DEMO-EARPHONES-01',
        ]);

        // Verify Affiliate code prefix
        $this->assertDatabaseHas('affiliates', [
            'tenant_id' => $this->tenant->id,
            'code' => 'DEMO-AFF-01',
        ]);

        // Verify Order reference prefix
        $this->assertDatabaseHas('lunar_orders', [
            'tenant_id' => $this->tenant->id,
            'reference' => 'DEMO-ORD-101',
        ]);
    }

    public function test_purge_demo_data_deletes_only_demo_records_and_retains_real_data(): void
    {
        /** @var TenantDemoDataService $demoService */
        $demoService = app(TenantDemoDataService::class);

        // 1. Seed demo data
        $demoService->seedDemoData($this->tenant);

        // 2. Add real merchant product and real affiliate
        $realVariantId = 0;
        $this->tenant->run(function () use (&$realVariantId) {
            $productType = ProductType::firstOrCreate(['name' => 'Standard']);
            $taxClass = TaxClass::firstOrCreate(['name' => 'Standard']);

            $realProduct = Product::create([
                'product_type_id' => $productType->id,
                'status' => 'published',
                'attribute_data' => ['name' => new Text('منتج تاجر حقيقي أصلي')],
            ]);

            $realVariant = ProductVariant::create([
                'product_id' => $realProduct->id,
                'sku' => 'REAL-MERCHANT-SKU-99',
                'tax_class_id' => $taxClass->id,
                'stock' => 150,
            ]);

            $realVariantId = $realVariant->id;
        });

        $realAffiliate = Affiliate::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'مسوق حقيقي غير تجريبي',
            'code' => 'REAL-TOP-AFFILIATE',
            'phone' => '+201000998877',
            'email' => 'real-merchant-aff@test.com',
            'password' => bcrypt('password123'),
            'status' => Affiliate::STATUS_ACTIVE,
        ]);

        // 3. Purge demo data
        $purgeResult = $demoService->purgeDemoData($this->tenant);

        $this->assertEquals(5, $purgeResult['purged_products']);
        $this->assertEquals(2, $purgeResult['purged_affiliates']);
        $this->assertEquals(3, $purgeResult['purged_orders']);
        $this->assertEquals(1, $purgeResult['purged_customers']);

        $this->assertFalse($demoService->hasDemoData($this->tenant));

        // 4. Assert real merchant product and real affiliate are STILL INTACT!
        $this->assertDatabaseHas('lunar_product_variants', [
            'id' => $realVariantId,
            'sku' => 'REAL-MERCHANT-SKU-99',
            'tenant_id' => $this->tenant->id,
        ]);

        $this->assertDatabaseHas('affiliates', [
            'id' => $realAffiliate->id,
            'code' => 'REAL-TOP-AFFILIATE',
            'tenant_id' => $this->tenant->id,
        ]);
    }

    public function test_store_demo_data_http_endpoints(): void
    {
        // 1. Seed Demo via HTTP POST
        $seedResponse = $this->actingAs($this->merchant)
            ->postJson("http://{$this->tenantDomain}/store/demo-data/seed");

        $seedResponse->assertStatus(200);
        $seedResponse->assertJson([
            'status' => 'success',
            'data' => [
                'products' => 5,
                'affiliates' => 2,
                'orders' => 3,
                'customers' => 1,
            ],
        ]);

        // 2. Purge Demo via HTTP POST
        $purgeResponse = $this->actingAs($this->merchant)
            ->postJson("http://{$this->tenantDomain}/store/demo-data/purge");

        $purgeResponse->assertStatus(200);
        $purgeResponse->assertJson([
            'status' => 'success',
            'data' => [
                'purged_products' => 5,
                'purged_affiliates' => 2,
                'purged_orders' => 3,
                'purged_customers' => 1,
            ],
        ]);
    }

    public function test_demo_data_artisan_commands(): void
    {
        // 1. Seed demo data via artisan command
        $seedExitCode = Artisan::call('tenant:seed-demo', ['tenant' => $this->tenant->id]);
        $this->assertEquals(0, $seedExitCode);

        $output = Artisan::output();
        $this->assertStringContainsString('Demo data seeded successfully', $output);

        // 2. Purge demo data via artisan command
        $purgeExitCode = Artisan::call('tenant:purge-demo', ['tenant' => $this->tenant->id]);
        $this->assertEquals(0, $purgeExitCode);

        $outputPurge = Artisan::output();
        $this->assertStringContainsString('Demo data purged', $outputPurge);

        // 3. Command with invalid tenant
        $invalidExit = Artisan::call('tenant:seed-demo', ['tenant' => 'non-existent-tenant-id']);
        $this->assertEquals(1, $invalidExit);
    }
}
