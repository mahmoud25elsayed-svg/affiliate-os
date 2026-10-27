<?php

declare(strict_types=1);

namespace Tests\Feature\Customer;

use App\Domain\Customer\Services\CustomerOtpService;
use App\Livewire\Storefront\CustomerLoginPage;
use App\Livewire\Storefront\CustomerRegisterPage;
use App\Models\Customer;
use App\Models\Tenant;
use App\Support\Phone\EgyptianPhone;
use Database\Seeders\EgyptGovernoratesSeeder;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class CustomerAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected string $domain;

    protected function setUp(): void
    {
        parent::setUp();

        app()->setLocale('ar');
        $this->seed(PlanSeeder::class);
        $this->seed(EgyptGovernoratesSeeder::class);
        $this->tenant = $this->ensureTenantExists('custstore');
        $this->domain = $this->tenant->domains->first()?->domain ?? 'custstore.test';
    }

    public function test_can_request_otp_with_valid_egyptian_phone(): void
    {
        $this->tenant->run(function () {
            $otpService = app(CustomerOtpService::class);
            $code = $otpService->requestOtp('01012345678');

            $this->assertNotEmpty($code);
            $this->assertSame(6, strlen($code));

            $phone = EgyptianPhone::parse('01012345678');
            $this->assertTrue(Cache::has("customer_otp_code_{$phone->hash()}"));
        });
    }

    public function test_request_otp_rejects_invalid_egyptian_phone(): void
    {
        $this->tenant->run(function () {
            $otpService = app(CustomerOtpService::class);

            $this->expectException(ValidationException::class);
            $otpService->requestOtp('01912345678'); // 019 does not exist in Egypt
        });
    }

    public function test_request_otp_is_rate_limited(): void
    {
        $this->tenant->run(function () {
            $otpService = app(CustomerOtpService::class);
            $phone = '01123456789';

            // 3 allowed requests
            $otpService->requestOtp($phone);
            $otpService->requestOtp($phone);
            $otpService->requestOtp($phone);

            // 4th request must throw rate limit exception
            $this->expectException(ValidationException::class);
            $otpService->requestOtp($phone);
        });
    }

    public function test_verify_otp_locks_account_after_five_failed_attempts(): void
    {
        $this->tenant->run(function () {
            $otpService = app(CustomerOtpService::class);
            $phone = '01234567890';
            $otpService->requestOtp($phone);

            for ($i = 1; $i <= 4; $i++) {
                try {
                    $otpService->verifyOtp($phone, '000000');
                    $this->fail('Should fail with invalid OTP');
                } catch (ValidationException $e) {
                    $this->assertStringContainsString('غير صحيح', $e->validator->errors()->first('code'));
                }
            }

            // 5th attempt must trigger lockout
            try {
                $otpService->verifyOtp($phone, '000000');
                $this->fail('Should fail and lock account');
            } catch (ValidationException $e) {
                $this->assertStringContainsString('تم قفل الحساب', $e->validator->errors()->first('code'));
            }

            // Even requesting OTP now is blocked by lockout
            $this->expectException(ValidationException::class);
            $otpService->requestOtp($phone);
        });
    }

    public function test_successful_otp_verification_creates_and_authenticates_customer(): void
    {
        $this->tenant->run(function () {
            $otpService = app(CustomerOtpService::class);
            $phone = '01512345678';
            $code = $otpService->requestOtp($phone);

            $customer = $otpService->verifyOtp($phone, $code);

            $this->assertInstanceOf(Customer::class, $customer);
            $this->assertSame('+201512345678', $customer->phone_e164);
            $this->assertNotNull($customer->phone_verified_at);
            $this->assertTrue($customer->is_active);
        });
    }

    public function test_livewire_login_component_with_otp_flow(): void
    {
        $this->tenant->run(function () {
            $phone = '01099887766';

            Livewire::test(CustomerLoginPage::class)
                ->set('phone', $phone)
                ->call('requestOtp')
                ->assertSet('otpSent', true)
                ->assertSee('تم إرسال رمز التحقق');

            $parsed = EgyptianPhone::parse($phone);
            $code = Cache::get("customer_otp_code_{$parsed->hash()}");
            $this->assertNotNull($code);

            Livewire::test(CustomerLoginPage::class)
                ->set('phone', $phone)
                ->set('otp', $code)
                ->call('verifyOtp')
                ->assertRedirect(route('storefront.account.orders'));

            $this->assertTrue(Auth::guard('customer')->check());
            $this->assertSame('+201099887766', Auth::guard('customer')->user()?->phone_e164);
        });
    }

    public function test_customer_can_register_and_login_with_email_and_password(): void
    {
        $this->tenant->run(function () {
            Livewire::test(CustomerRegisterPage::class)
                ->set('first_name', 'أحمد')
                ->set('last_name', 'محمود')
                ->set('phone', '01055443322')
                ->set('email', 'ahmed@example.com')
                ->set('password', 'secret123')
                ->set('password_confirmation', 'secret123')
                ->call('register')
                ->assertRedirect(route('storefront.account.orders'));

            $this->assertTrue(Auth::guard('customer')->check());
            $this->assertSame('ahmed@example.com', Auth::guard('customer')->user()?->email);

            Auth::guard('customer')->logout();
            $this->assertFalse(Auth::guard('customer')->check());

            // Login with password
            Livewire::test(CustomerLoginPage::class)
                ->set('mode', 'password')
                ->set('email', 'ahmed@example.com')
                ->set('password', 'secret123')
                ->call('loginWithPassword')
                ->assertRedirect(route('storefront.account.orders'));

            $this->assertTrue(Auth::guard('customer')->check());
        });
    }

    public function test_password_login_rejects_incorrect_password_and_deactivated_accounts(): void
    {
        $this->tenant->run(function () {
            Customer::create([
                'tenant_id' => $this->tenant->id,
                'first_name' => 'سامي',
                'email' => 'sami@example.com',
                'password' => Hash::make('password123'),
                'is_active' => false, // deactivated
            ]);

            // Incorrect password
            Livewire::test(CustomerLoginPage::class)
                ->set('mode', 'password')
                ->set('email', 'sami@example.com')
                ->set('password', 'wrong-pass')
                ->call('loginWithPassword')
                ->assertSee('غير صحيحة');

            $this->assertFalse(Auth::guard('customer')->check());

            // Deactivated account
            Livewire::test(CustomerLoginPage::class)
                ->set('mode', 'password')
                ->set('email', 'sami@example.com')
                ->set('password', 'password123')
                ->call('loginWithPassword')
                ->assertSee('تعطيل هذا الحساب');

            $this->assertFalse(Auth::guard('customer')->check());
        });
    }
}
