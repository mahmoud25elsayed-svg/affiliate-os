<?php

declare(strict_types=1);

namespace Tests\Feature\Mobile;

use App\Domain\Notifications\Enums\NotificationChannel;
use App\Domain\Notifications\Services\MobilePushNotificationService;
use App\Models\Affiliate;
use App\Models\ApiKey;
use App\Models\Domain;
use App\Models\MobileDevice;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy\Concerns\BelongsToTenant;
use App\Support\Tenancy\Permissions\TenantPermissionMatrix;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class MobileAppsArchitectureTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected string $domain;

    protected User $owner;

    protected Plan $proPlan;

    protected Affiliate $affiliate;

    protected MobilePushNotificationService $pushService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PlanSeeder::class);
        $this->proPlan = Plan::where('slug', 'pro')->firstOrFail();

        $this->tenant = $this->ensureTenantExists('mobile-test-store');
        /** @var Domain|null $firstDomain */
        $firstDomain = $this->tenant->domains->first();
        $this->domain = $firstDomain ? (string) $firstDomain->domain : 'mobile-test-store.localhost';

        $this->owner = User::where('email', 'owner@mobile-test-store.test')->firstOrFail();
        TenantPermissionMatrix::seedForTenant((string) $this->tenant->id);
        TenantPermissionMatrix::assignRole($this->owner, TenantPermissionMatrix::ROLE_OWNER, (string) $this->tenant->id);

        Subscription::create([
            'tenant_id' => $this->tenant->id,
            'plan_id' => $this->proPlan->id,
            'status' => Subscription::STATUS_ACTIVE,
            'billing_cycle' => Subscription::CYCLE_MONTHLY,
            'current_period_starts_at' => now()->startOfMonth(),
            'current_period_ends_at' => now()->addMonth(),
        ]);

        $this->tenant->run(function () {
            $this->affiliate = Affiliate::create([
                'tenant_id' => $this->tenant->id,
                'name' => 'مسوق تجريبي',
                'phone' => '+201099887766',
                'email' => 'affiliate.mobile@test.com',
                'password' => bcrypt('secret123'),
                'code' => 'MOBAFF01',
                'status' => Affiliate::STATUS_ACTIVE,
                'phone_verified_at' => now(),
            ]);
        });

        $this->pushService = app(MobilePushNotificationService::class);
    }

    public function test_it_registers_a_mobile_device_successfully_via_api(): void
    {
        $createdKey = ApiKey::createKey(
            tenant: $this->tenant,
            name: 'Mobile App Device Registration Key',
            scopes: [ApiKey::SCOPE_MOBILE_DEVICES_MANAGE],
            owner: $this->owner,
        );

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$createdKey['plainTextKey'],
            'Host' => $this->domain,
        ])->postJson('/api/v1/mobile/devices', [
            'device_token' => 'fcm_sample_token_ios_device_123',
            'platform' => MobileDevice::PLATFORM_IOS,
            'app_type' => MobileDevice::APP_TYPE_AFFILIATE,
            'device_name' => 'iPhone 15 Pro Max',
            'app_version' => '1.0.0',
            'locale' => 'ar',
            'affiliate_id' => $this->affiliate->id,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Mobile device registered successfully.',
                'data' => [
                    'device_token' => 'fcm_sample_token_ios_device_123',
                    'platform' => 'ios',
                    'app_type' => 'affiliate',
                    'affiliate_id' => $this->affiliate->id,
                    'is_active' => true,
                ],
            ]);

        $this->assertDatabaseHas('mobile_devices', [
            'tenant_id' => $this->tenant->id,
            'device_token' => 'fcm_sample_token_ios_device_123',
            'platform' => 'ios',
            'app_type' => 'affiliate',
            'affiliate_id' => $this->affiliate->id,
            'is_active' => true,
        ]);
    }

    public function test_it_rejects_registration_without_mobile_devices_manage_scope(): void
    {
        // Key with catalog read only
        $createdKey = ApiKey::createKey(
            tenant: $this->tenant,
            name: 'Limited Scope Key',
            scopes: [ApiKey::SCOPE_AFFILIATE_CATALOG_READ],
            owner: $this->owner,
        );

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$createdKey['plainTextKey'],
            'Host' => $this->domain,
        ])->postJson('/api/v1/mobile/devices', [
            'device_token' => 'token_without_scope',
            'platform' => 'android',
            'app_type' => 'customer',
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'error' => 'Forbidden',
                'message' => 'API Key lacks required scope: [mobile:devices:manage].',
            ]);
    }

    public function test_it_validates_platform_and_app_type_on_registration(): void
    {
        $createdKey = ApiKey::createKey(
            tenant: $this->tenant,
            name: 'Validation Key',
            scopes: [ApiKey::SCOPE_MOBILE_DEVICES_MANAGE],
            owner: $this->owner,
        );

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$createdKey['plainTextKey'],
            'Host' => $this->domain,
        ])->postJson('/api/v1/mobile/devices', [
            'device_token' => 'some_token',
            'platform' => 'windows_phone', // Invalid
            'app_type' => 'invalid_app',  // Invalid
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['platform', 'app_type']);
    }

    public function test_it_updates_existing_device_token_upon_re_registration(): void
    {
        $createdKey = ApiKey::createKey(
            tenant: $this->tenant,
            name: 'Re-Registration Key',
            scopes: [ApiKey::SCOPE_MOBILE_DEVICES_MANAGE],
            owner: $this->owner,
        );

        // Initial registration
        $this->withHeaders([
            'Authorization' => 'Bearer '.$createdKey['plainTextKey'],
            'Host' => $this->domain,
        ])->postJson('/api/v1/mobile/devices', [
            'device_token' => 'fcm_rotating_token_999',
            'platform' => 'android',
            'app_type' => 'merchant',
            'app_version' => '1.0.0',
        ])->assertStatus(200);

        // Subsequent registration with updated app version
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$createdKey['plainTextKey'],
            'Host' => $this->domain,
        ])->postJson('/api/v1/mobile/devices', [
            'device_token' => 'fcm_rotating_token_999',
            'platform' => 'android',
            'app_type' => 'merchant',
            'app_version' => '1.0.1',
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseCount('mobile_devices', 1);
        $this->assertDatabaseHas('mobile_devices', [
            'tenant_id' => $this->tenant->id,
            'device_token' => 'fcm_rotating_token_999',
            'app_version' => '1.0.1',
            'is_active' => true,
        ]);
    }

    public function test_it_unregisters_mobile_device_via_api(): void
    {
        $createdKey = ApiKey::createKey(
            tenant: $this->tenant,
            name: 'Unregister Key',
            scopes: [ApiKey::SCOPE_MOBILE_DEVICES_MANAGE],
            owner: $this->owner,
        );

        // Seed a device first
        $this->tenant->run(function () {
            MobileDevice::create([
                'tenant_id' => $this->tenant->id,
                'device_token' => 'token_to_unregister_xyz',
                'platform' => 'ios',
                'app_type' => 'affiliate',
                'is_active' => true,
            ]);
        });

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$createdKey['plainTextKey'],
            'Host' => $this->domain,
        ])->deleteJson('/api/v1/mobile/devices/token_to_unregister_xyz');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Mobile device unregistered successfully.',
            ]);

        $this->assertDatabaseHas('mobile_devices', [
            'tenant_id' => $this->tenant->id,
            'device_token' => 'token_to_unregister_xyz',
            'is_active' => false,
        ]);
    }

    public function test_it_returns_dynamic_white_label_mobile_config(): void
    {
        $createdKey = ApiKey::createKey(
            tenant: $this->tenant,
            name: 'Config Key',
            scopes: [ApiKey::SCOPE_MOBILE_DEVICES_MANAGE],
            owner: $this->owner,
        );

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$createdKey['plainTextKey'],
            'Host' => $this->domain,
        ])->getJson('/api/v1/mobile/config');

        $response->assertStatus(200)
            ->assertJson([
                'tenant_id' => $this->tenant->id,
                'currency' => 'EGP',
                'locale' => 'ar',
                'branding' => [
                    'primary_color' => '#10B981',
                    'font_family' => 'Cairo',
                ],
                'features' => [
                    'affiliate_portal' => true,
                    'cod_checkout' => true,
                    'order_tracking' => true,
                    'biometric_auth' => true,
                    'camera_kyc' => true,
                ],
                'api_version' => 'v1',
            ]);
    }

    public function test_it_registers_and_dispatches_push_notifications_via_service(): void
    {
        Log::shouldReceive('info')
            ->atLeast()->once();

        $device = $this->pushService->registerDevice($this->tenant, [
            'device_token' => 'service_test_token_888',
            'platform' => MobileDevice::PLATFORM_ANDROID,
            'app_type' => MobileDevice::APP_TYPE_AFFILIATE,
            'affiliate_id' => $this->affiliate->id,
            'device_name' => 'Pixel 8 Pro',
        ]);

        $this->assertTrue($device->is_active);
        $this->assertNotNull($device->last_active_at);

        // Format payload
        $payload = $this->pushService->formatPayload(
            $device,
            '🎉 مبروك! عمولة جديدة',
            'تم تسجيل طلب جديد برقم #ORD-101. عمولتك: 150.00 ج.م',
            ['order_id' => 101, 'amount' => 15000]
        );

        $this->assertEquals('service_test_token_888', $payload['to']);
        $this->assertEquals('android', $payload['platform']);
        $this->assertEquals('affiliate', $payload['app_type']);
        $this->assertEquals('101', $payload['data']['order_id']);

        // Dispatch notification to affiliate
        $result = $this->pushService->sendToAffiliate(
            $this->tenant,
            $this->affiliate,
            '🎉 مبروك! عمولة جديدة',
            'تم تسجيل طلب جديد برقم #ORD-101. عمولتك: 150.00 ج.م',
            ['order_id' => 101]
        );

        $this->assertEquals(1, $result['dispatched']);
        $this->assertContains('service_test_token_888', $result['tokens']);
    }

    public function test_it_filters_devices_by_affiliate_and_merchant_in_service(): void
    {
        $this->pushService->registerDevice($this->tenant, [
            'device_token' => 'token_affiliate_1',
            'platform' => MobileDevice::PLATFORM_IOS,
            'app_type' => MobileDevice::APP_TYPE_AFFILIATE,
            'affiliate_id' => $this->affiliate->id,
        ]);

        $this->pushService->registerDevice($this->tenant, [
            'device_token' => 'token_merchant_1',
            'platform' => MobileDevice::PLATFORM_ANDROID,
            'app_type' => MobileDevice::APP_TYPE_MERCHANT,
            'user_id' => $this->owner->id,
        ]);

        $affiliateDevices = $this->pushService->getDevicesForAffiliate($this->tenant, $this->affiliate);
        $this->assertCount(1, $affiliateDevices);
        $this->assertEquals('token_affiliate_1', $affiliateDevices->first()?->device_token);

        $merchantDevices = $this->pushService->getDevicesForMerchant($this->tenant);
        $this->assertCount(1, $merchantDevices);
        $this->assertEquals('token_merchant_1', $merchantDevices->first()?->device_token);

        // Unregister affiliate device
        $unregistered = $this->pushService->unregisterDevice($this->tenant, 'token_affiliate_1');
        $this->assertTrue($unregistered);

        $activeAffiliateDevices = $this->pushService->getDevicesForAffiliate($this->tenant, $this->affiliate);
        $this->assertCount(0, $activeAffiliateDevices);
    }

    public function test_it_verifies_notification_channel_has_mobile_push(): void
    {
        $channel = NotificationChannel::MOBILE_PUSH;
        $this->assertEquals('mobile_push', $channel->value);
        $this->assertStringContainsString('Mobile Push', $channel->getLabel());
    }

    public function test_it_verifies_mobile_device_model_uses_belongs_to_tenant_trait(): void
    {
        $traits = class_uses_recursive(MobileDevice::class);
        $this->assertArrayHasKey(BelongsToTenant::class, $traits);
    }

    public function test_it_verifies_mobile_architecture_document_exists_and_covers_all_p40_specifications(): void
    {
        $docPath = base_path('docs/18-mobile-apps-architecture.md');
        $this->assertFileExists($docPath);

        $content = (string) file_get_contents($docPath);

        // 1. Flutter vs React Native evaluation
        $this->assertStringContainsString('React Native', $content);
        $this->assertStringContainsString('Flutter', $content);
        $this->assertStringContainsString('Expo', $content);

        // 2. Monorepo proposal
        $this->assertStringContainsString('Monorepo', $content);
        $this->assertStringContainsString('Turborepo', $content);
        $this->assertStringContainsString('apps/customer-app', $content);
        $this->assertStringContainsString('apps/affiliate-app', $content);
        $this->assertStringContainsString('apps/merchant-app', $content);

        // 3. App A: White-Label Customer Storefront App
        $this->assertStringContainsString('White-Label Customer Storefront', $content);
        $this->assertStringContainsString('app.config.ts', $content);
        $this->assertStringContainsString('COD', $content);

        // 4. App B: Affiliate Mobile App + Push + Camera KYC
        $this->assertStringContainsString('Affiliate Mobile App', $content);
        $this->assertStringContainsString('PWA', $content);
        $this->assertStringContainsString('Push', $content);
        $this->assertStringContainsString('KYC', $content);
        $this->assertStringContainsString('Biometric', $content);

        // 5. App C: Merchant Companion App
        $this->assertStringContainsString('Merchant Companion App', $content);
        $this->assertStringContainsString('GMV', $content);
        $this->assertStringContainsString('Barcode', $content);

        // 6. Publishing Pipeline
        $this->assertStringContainsString('Fastlane', $content);
        $this->assertStringContainsString('TestFlight', $content);
        $this->assertStringContainsString('Google Play', $content);
    }
}
