<?php

declare(strict_types=1);

namespace Tests\Feature\Tenancy;

use App\Domain\Tenancy\Contracts\DnsResolverInterface;
use App\Domain\Tenancy\Contracts\HostingerClientInterface;
use App\Domain\Tenancy\Services\CustomDomainService;
use App\Models\CustomPage;
use App\Models\Domain;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Billing\Exceptions\FeatureNotAvailableException;
use App\Support\Settings\ThemeSettings;
use App\Support\Tenancy\Permissions\TenantPermissionMatrix;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Lunar\Models\Channel;
use Lunar\Models\Currency;
use Lunar\Models\Language;
use Tests\TestCase;

class CustomDomainAndThemesTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected User $owner;

    protected Plan $starterPlan;

    protected Plan $proPlan;

    protected Plan $enterprisePlan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PlanSeeder::class);

        $this->starterPlan = Plan::where('slug', 'starter')->firstOrFail();
        $this->proPlan = Plan::where('slug', 'pro')->firstOrFail();
        $this->enterprisePlan = Plan::where('slug', 'enterprise')->firstOrFail();

        // Create tenant with Pro plan by default
        $this->tenant = Tenant::create([
            'id' => 'cairo-electronics',
            'name' => 'Cairo Electronics',
            'status' => Tenant::STATUS_ACTIVE,
        ]);

        Subscription::create([
            'tenant_id' => $this->tenant->id,
            'plan_id' => $this->proPlan->id,
            'status' => Subscription::STATUS_ACTIVE,
            'starts_at' => now()->subDay(),
            'renews_at' => now()->addMonth(),
        ]);

        // Default subdomain
        Domain::create([
            'domain' => 'cairo-electronics.affiliate-os.test',
            'tenant_id' => $this->tenant->id,
            'is_primary' => true,
            'is_custom' => false,
            'status' => Domain::STATUS_ACTIVE,
            'verified_at' => now(),
            'ssl_status' => Domain::SSL_STATUS_ACTIVE,
        ]);

        $this->owner = User::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);
        TenantPermissionMatrix::seedForTenant($this->tenant->id);
        TenantPermissionMatrix::assignRole($this->owner, TenantPermissionMatrix::ROLE_OWNER, $this->tenant->id);

        $this->tenant->run(function () {
            Channel::firstOrCreate(
                ['handle' => 'storefront'],
                ['name' => 'Storefront Channel', 'default' => true, 'url' => 'http://localhost']
            );
            Currency::firstOrCreate(
                ['code' => 'EGP'],
                ['name' => 'Egyptian Pound', 'exchange_rate' => 1.0000, 'decimal_places' => 2, 'enabled' => true, 'default' => true]
            );
            Language::firstOrCreate(
                ['code' => 'ar'],
                ['name' => 'Arabic', 'default' => true]
            );
        });
    }

    public function test_starter_plan_cannot_add_custom_domain(): void
    {
        // Switch tenant subscription to Starter plan
        $this->tenant->subscription()->update(['plan_id' => $this->starterPlan->id]);

        /** @var CustomDomainService $service */
        $service = app(CustomDomainService::class);

        $this->expectException(FeatureNotAvailableException::class);
        $service->addCustomDomain($this->tenant, 'shop.cairoelectronics.com');
    }

    public function test_pro_plan_can_add_custom_domain_with_verification_records(): void
    {
        /** @var CustomDomainService $service */
        $service = app(CustomDomainService::class);

        $domain = $service->addCustomDomain($this->tenant, 'https://SHOP.CairoElectronics.com/');

        $this->assertInstanceOf(Domain::class, $domain);
        $this->assertSame('shop.cairoelectronics.com', $domain->domain);
        $this->assertTrue($domain->is_custom);
        $this->assertFalse($domain->is_primary);
        $this->assertSame(Domain::STATUS_PENDING_VERIFICATION, $domain->status);
        $this->assertStringStartsWith('aff_verify_', (string) $domain->verification_token);
        $this->assertIsArray($domain->dns_records);
        $this->assertArrayHasKey('txt', $domain->dns_records);
        $this->assertArrayHasKey('cname', $domain->dns_records);
        $this->assertSame('_affiliate-os-challenge.shop.cairoelectronics.com', $domain->dns_records['txt']['host']);
    }

    public function test_custom_domain_validation_rejects_invalid_and_reserved_domains(): void
    {
        /** @var CustomDomainService $service */
        $service = app(CustomDomainService::class);

        // Reserved central domain
        $this->expectException(InvalidArgumentException::class);
        $service->addCustomDomain($this->tenant, 'localhost');
    }

    public function test_dns_verification_activates_custom_domain_and_triggers_hostinger_ssl(): void
    {
        /** @var CustomDomainService $service */
        $service = app(CustomDomainService::class);

        $domain = $service->addCustomDomain($this->tenant, 'shop.cairoelectronics.com');

        // Mock DNS resolver to return expected challenge token
        $mockDns = new class((string) $domain->verification_token) implements DnsResolverInterface
        {
            public function __construct(protected string $token) {}

            public function getTxtRecords(string $hostname): array
            {
                return [$this->token];
            }

            public function getCnameRecord(string $hostname): ?string
            {
                return 'cname.affiliate-os.com';
            }

            public function getARecords(string $hostname): array
            {
                return ['127.0.0.1'];
            }
        };

        $mockHostinger = new class implements HostingerClientInterface
        {
            public bool $parkedCalled = false;

            public bool $sslCalled = false;

            public function createParkedDomain(string $customDomain): bool
            {
                $this->parkedCalled = true;

                return true;
            }

            public function requestSsl(string $customDomain): bool
            {
                $this->sslCalled = true;

                return true;
            }

            public function getSslStatus(string $customDomain): array
            {
                return ['status' => 'active', 'provider' => 'Hostinger SSL', 'expires_at' => null, 'error' => null];
            }

            public function removeParkedDomain(string $customDomain): bool
            {
                return true;
            }
        };

        $serviceWithMocks = new CustomDomainService($mockDns, $mockHostinger);

        $verified = $serviceWithMocks->verifyDomain($domain);

        $this->assertTrue($verified);
        $this->assertTrue($mockHostinger->parkedCalled);
        $this->assertTrue($mockHostinger->sslCalled);

        $domain->refresh();
        $this->assertSame(Domain::STATUS_ACTIVE, $domain->status);
        $this->assertNotNull($domain->verified_at);
        $this->assertSame(Domain::SSL_STATUS_INSTALLING, $domain->ssl_status);
        $this->assertSame('hostinger', $domain->ssl_provider);
    }

    public function test_tenant_identification_by_custom_domain(): void
    {
        // Add active custom domain
        $customDomain = Domain::create([
            'domain' => 'shop.cairoelectronics.com',
            'tenant_id' => $this->tenant->id,
            'is_primary' => true,
            'is_custom' => true,
            'status' => Domain::STATUS_ACTIVE,
            'verified_at' => now(),
            'ssl_status' => Domain::SSL_STATUS_ACTIVE,
        ]);

        $response = $this->get('http://shop.cairoelectronics.com/');

        $response->assertStatus(200);
        $this->assertSame($this->tenant->id, tenant('id'));
    }

    public function test_subdomain_redirects_to_primary_custom_domain_when_enabled(): void
    {
        /** @var CustomDomainService $service */
        $service = app(CustomDomainService::class);

        // Add and activate custom domain as primary
        $customDomain = Domain::create([
            'domain' => 'shop.cairoelectronics.com',
            'tenant_id' => $this->tenant->id,
            'is_primary' => true,
            'is_custom' => true,
            'status' => Domain::STATUS_ACTIVE,
            'verified_at' => now(),
            'ssl_status' => Domain::SSL_STATUS_ACTIVE,
        ]);

        // Unset primary on subdomain
        Domain::where('tenant_id', $this->tenant->id)
            ->where('is_custom', false)
            ->update(['is_primary' => false]);

        // Enable redirection on subdomain
        $service->setSubdomainRedirection($this->tenant, true);

        $response = $this->get('http://cairo-electronics.affiliate-os.test/products');

        $response->assertStatus(301);
        $response->assertRedirect('http://shop.cairoelectronics.com/products');
    }

    public function test_theme_customizer_and_boutique_theme_rendering(): void
    {
        $this->actingAs($this->owner);

        // 1. Visit customizer view
        $response = $this->get('http://cairo-electronics.affiliate-os.test/store/theme');
        $response->assertStatus(200);
        $response->assertSee('تخصيص الثيم والمظهر');
        $response->assertSee('بوتيك النخبة');

        // 2. Update to boutique theme
        $updateResponse = $this->post('http://cairo-electronics.affiliate-os.test/store/theme', [
            'theme_id' => 'boutique',
            'primary_color' => '#d4af37',
            'secondary_color' => '#1c1917',
            'accent_color' => '#b45309',
            'font_family' => 'Almarai',
            'hero_headline' => 'أناقة لا تضاهى لكل مناسبة',
            'hero_subheadline' => 'أفخم التشكيلات الأصلية مع شحن فاخر وسريع',
            'hero_button_text' => 'اكتشف البوتيك',
            'hero_button_url' => '/products',
            'show_hero_banner' => 1,
            'show_features_badges' => 1,
            'show_featured_categories' => 1,
            'show_featured_products' => 1,
        ]);

        $updateResponse->assertRedirect('http://cairo-electronics.affiliate-os.test/store/theme');

        // 3. Visit storefront home and verify boutique theme is active
        $homeResponse = $this->get('http://cairo-electronics.affiliate-os.test/');
        $homeResponse->assertStatus(200);
        $homeResponse->assertSee('أناقة لا تضاهى لكل مناسبة');
        $homeResponse->assertSee('تشكيلة الموسم الحصرية • بوتيك فاخر');
    }

    public function test_white_label_enforcement_by_plan(): void
    {
        // 1. Pro plan: white_label feature is false
        $this->tenant->run(function () {
            $theme = ThemeSettings::load();
            $theme->white_label_enabled = true; // Attempt to enable
            $theme->save();
        });

        $responsePro = $this->get('http://cairo-electronics.affiliate-os.test/');
        $responsePro->assertStatus(200);
        $responsePro->assertSee('مشغل بواسطة منصة التجارة والتسويق بالعمولة');

        // 2. Switch to Enterprise plan: white_label feature is true
        $this->tenant->subscription()->update(['plan_id' => $this->enterprisePlan->id]);
        $this->tenant->unsetRelation('subscription');

        $responseEnterprise = $this->get('http://cairo-electronics.affiliate-os.test/');
        $responseEnterprise->assertStatus(200);
        $responseEnterprise->assertDontSee('مشغل بواسطة منصة التجارة والتسويق بالعمولة');
    }

    public function test_custom_pages_rendering_and_tenant_isolation(): void
    {
        // 1. Create custom page for tenant
        $this->tenant->run(function () {
            CustomPage::create([
                'title' => 'الأسئلة الشائعة وتجارب العملاء',
                'slug' => 'faq-and-reviews',
                'content' => 'نقدم لكم إجابات وافية عن كافة تساؤلاتكم حول الشحن وضمان الاسترجاع والدفع عند الاستلام.',
                'is_published' => true,
            ]);
        });

        $response = $this->get('http://cairo-electronics.affiliate-os.test/pages/faq-and-reviews');
        $response->assertStatus(200);
        $response->assertSee('الأسئلة الشائعة وتجارب العملاء');
        $response->assertSee('إجابات وافية عن كافة تساؤلاتكم');

        // 2. Another tenant cannot see this page
        $otherTenant = Tenant::create([
            'id' => 'delta-fashion',
            'name' => 'Delta Fashion',
            'status' => Tenant::STATUS_ACTIVE,
        ]);

        Subscription::create([
            'tenant_id' => $otherTenant->id,
            'plan_id' => $this->proPlan->id,
            'status' => Subscription::STATUS_ACTIVE,
            'starts_at' => now(),
            'renews_at' => now()->addMonth(),
        ]);

        Domain::create([
            'domain' => 'delta-fashion.affiliate-os.test',
            'tenant_id' => $otherTenant->id,
            'is_primary' => true,
            'is_custom' => false,
            'status' => Domain::STATUS_ACTIVE,
            'verified_at' => now(),
        ]);

        $responseOther = $this->get('http://delta-fashion.affiliate-os.test/pages/faq-and-reviews');
        $responseOther->assertStatus(404);
    }
}
