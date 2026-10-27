<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Support\Auth\TotpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TwoFactorTest extends TestCase
{
    use RefreshDatabase;

    public function test_totp_service_generates_and_verifies_rfc6238_codes(): void
    {
        $service = new TotpService;
        $secret = $service->generateSecret(16);

        $this->assertEquals(16, strlen($secret));

        // Generate OTP for current timestamp
        $otp = $service->getOtp($secret);
        $this->assertEquals(6, strlen($otp));
        $this->assertMatchesRegularExpression('/^[0-9]{6}$/', $otp);

        // Verify valid OTP
        $this->assertTrue($service->verify($secret, $otp));

        // Reject invalid OTP
        $this->assertFalse($service->verify($secret, $otp === '000000' ? '999999' : '000000'));
    }

    public function test_platform_admin_without_two_factor_is_redirected_to_setup(): void
    {
        $admin = User::create([
            'name' => 'Platform Admin',
            'email' => 'admin@platform.test',
            'password' => 'secret123',
            'is_platform_admin' => true,
            'two_factor_secret' => null,
            'two_factor_confirmed_at' => null,
        ]);

        $response = $this->actingAs($admin)->get('/platform');
        $response->assertRedirect(route('platform.2fa.setup'));
    }

    public function test_platform_admin_can_setup_and_confirm_two_factor(): void
    {
        $admin = User::create([
            'name' => 'Platform Admin',
            'email' => 'admin2@platform.test',
            'password' => 'secret123',
            'is_platform_admin' => true,
        ]);

        $this->actingAs($admin)->get(route('platform.2fa.setup'));

        $secret = session()->get('two_factor_setup_secret');
        $this->assertNotNull($secret);

        $service = app(TotpService::class);
        $validCode = $service->getOtp($secret);

        $response = $this->actingAs($admin)->post(route('platform.2fa.confirm'), [
            'code' => $validCode,
        ]);

        $response->assertRedirect('/platform');

        $admin->refresh();
        $this->assertTrue($admin->hasTwoFactorEnabled());
        $this->assertTrue(session()->get('two_factor_authenticated'));
    }
}
