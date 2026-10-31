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

    private function adminWithTwoFactor(): User
    {
        $admin = new User;
        $admin->forceFill([
            'name' => 'Platform Admin',
            'email' => 'admin3@platform.test',
            'password' => 'secret123',
            'is_platform_admin' => true,
            'two_factor_secret' => app(TotpService::class)->generateSecret(32),
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => ['ABCDE-12345', 'FGHIJ-67890'],
        ])->save();

        return $admin;
    }

    public function test_challenge_accepts_a_recovery_code_once(): void
    {
        $admin = $this->adminWithTwoFactor();

        $this->actingAs($admin)
            ->post(route('platform.2fa.verify'), ['code' => ' abcde-12345 '])
            ->assertRedirect('/platform');
        $this->assertTrue(session()->get('two_factor_authenticated'));
        $this->assertSame(['FGHIJ-67890'], $admin->refresh()->two_factor_recovery_codes);

        session()->forget('two_factor_authenticated');
        $this->actingAs($admin)
            ->post(route('platform.2fa.verify'), ['code' => 'ABCDE-12345'])
            ->assertSessionHasErrors('code');
        $this->assertNull(session()->get('two_factor_authenticated'));
    }

    public function test_challenge_accepts_totp_and_rejects_wrong_codes(): void
    {
        $admin = $this->adminWithTwoFactor();
        $otp = app(TotpService::class)->getOtp((string) $admin->two_factor_secret);

        $this->actingAs($admin)
            ->post(route('platform.2fa.verify'), ['code' => $otp === '000000' ? '111111' : '000000'])
            ->assertSessionHasErrors('code');

        $this->actingAs($admin)
            ->post(route('platform.2fa.verify'), ['code' => $otp])
            ->assertRedirect('/platform');
        $this->assertCount(2, (array) $admin->refresh()->two_factor_recovery_codes);
    }
}
