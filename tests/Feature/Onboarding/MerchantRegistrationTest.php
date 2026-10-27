<?php

declare(strict_types=1);

namespace Tests\Feature\Onboarding;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class MerchantRegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        Notification::fake();
    }

    public function test_landing_page_renders_successfully(): void
    {
        $response = $this->get(route('landing'));

        $response->assertStatus(200);
        $response->assertSee('AffiliateOS');
        $response->assertSee('سجل متجرك الآن مجاناً');
    }

    public function test_registration_form_renders_successfully(): void
    {
        $response = $this->get(route('onboarding.register'));

        $response->assertStatus(200);
        $response->assertSee('سجل متجرك الآن مجاناً');
        $response->assertSee('النطاق الفرعي');
    }

    public function test_subdomain_availability_check_identifies_reserved_and_available_slugs(): void
    {
        // 1. Reserved subdomain check
        $response = $this->getJson(route('onboarding.check-subdomain', ['subdomain' => 'admin']));
        $response->assertStatus(200);
        $response->assertJson([
            'available' => false,
        ]);

        // 2. Format invalid (too short)
        $response = $this->getJson(route('onboarding.check-subdomain', ['subdomain' => 'ab']));
        $response->assertStatus(200);
        $response->assertJson([
            'available' => false,
        ]);

        // 3. Available subdomain
        $response = $this->getJson(route('onboarding.check-subdomain', ['subdomain' => 'cairo-boutique']));
        $response->assertStatus(200);
        $response->assertJson([
            'available' => true,
        ]);

        // 4. Already existing tenant
        Tenant::create(['id' => 'existing-store', 'name' => 'Existing', 'status' => 'active']);
        $response = $this->getJson(route('onboarding.check-subdomain', ['subdomain' => 'existing-store']));
        $response->assertStatus(200);
        $response->assertJson([
            'available' => false,
        ]);
    }

    public function test_otp_generation_and_rate_limiting(): void
    {
        $phone = '01012345678';

        // 1. Send OTP successfully
        $response = $this->postJson(route('onboarding.send-otp'), ['phone' => $phone]);
        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        // 2. Request OTP 2 more times (total 3 attempts)
        $this->postJson(route('onboarding.send-otp'), ['phone' => $phone]);
        $this->postJson(route('onboarding.send-otp'), ['phone' => $phone]);

        // 4th attempt exceeds 3/min limit
        $response = $this->postJson(route('onboarding.send-otp'), ['phone' => $phone]);
        $response->assertStatus(422);
        $response->assertJson(['success' => false]);
    }

    public function test_merchant_can_register_and_trigger_provisioning(): void
    {
        $payload = [
            'name' => 'محمود السيد',
            'store_name' => 'متجر النيل للإلكترونيات',
            'subdomain' => 'nile-tech',
            'phone' => '01012345678',
            'otp' => '123456',
            'email' => 'merchant@niletech.test',
            'password' => 'secret123456',
        ];

        $response = $this->postJson(route('onboarding.register.submit'), $payload);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'tenant_id' => 'nile-tech',
        ]);

        // Assert Tenant was created and provisioned
        $tenant = Tenant::find('nile-tech');
        $this->assertNotNull($tenant);
        $this->assertEquals('active', $tenant->status);
        $this->assertEquals('متجر النيل للإلكترونيات', $tenant->name);

        // Assert Owner user was created and logged in
        $this->assertAuthenticated();
        /** @var User $currentUser */
        $currentUser = auth()->user();
        $this->assertEquals('merchant@niletech.test', $currentUser->email);
        $this->assertEquals('nile-tech', $currentUser->tenant_id);
        $this->assertEquals('+201012345678', $currentUser->phone);
    }

    public function test_registration_rejects_reserved_subdomain(): void
    {
        $payload = [
            'name' => 'محمود السيد',
            'store_name' => 'منصة رئيسية',
            'subdomain' => 'platform',
            'phone' => '01012345678',
            'otp' => '123456',
            'email' => 'admin@platform.test',
            'password' => 'secret123456',
        ];

        $response = $this->postJson(route('onboarding.register.submit'), $payload);
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['subdomain']);
    }

    public function test_registration_rejects_invalid_egyptian_phone(): void
    {
        $payload = [
            'name' => 'محمود السيد',
            'store_name' => 'متجر تجريبي',
            'subdomain' => 'valid-store',
            'phone' => '01312345678', // Invalid Egyptian prefix
            'otp' => '123456',
            'email' => 'valid@store.test',
            'password' => 'secret123456',
        ];

        $response = $this->postJson(route('onboarding.register.submit'), $payload);
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['phone']);
    }

    public function test_provisioning_status_endpoint_returns_readiness(): void
    {
        $tenant = Tenant::create([
            'id' => 'status-store',
            'name' => 'Status Store',
            'status' => 'active',
        ]);

        $response = $this->getJson(route('onboarding.provisioning', ['tenant' => $tenant->id]));
        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'active',
            'ready' => true,
        ]);
    }
}
