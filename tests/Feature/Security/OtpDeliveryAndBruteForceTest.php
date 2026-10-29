<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Domain\Affiliate\Services\AffiliatePayoutAccountService;
use App\Domain\Affiliate\Services\AffiliateRegistrationService;
use App\Domain\Customer\Services\CustomerOtpService;
use App\Models\Affiliate;
use App\Models\NotificationLog;
use App\Models\Tenant;
use App\Support\Auth\OtpService;
use Database\Seeders\PlanSeeder;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Tests\TestCase;

class OtpDeliveryAndBruteForceTest extends TestCase
{
    protected Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PlanSeeder::class);
        $this->tenant = $this->ensureTenantExists('otpstore');

        config([
            'services.smsmisr.username' => 'sms-user',
            'services.smsmisr.password' => 'sms-pass',
            'services.smsmisr.endpoint' => 'https://sms.example.test/api/',
        ]);
        Http::fake(['sms.example.test/*' => Http::response(['code' => '1901', 'SMSID' => 'abc'])]);
    }

    public function test_merchant_onboarding_otp_is_actually_sent_by_sms(): void
    {
        $result = app(OtpService::class)->sendOtp('01012345678', '10.0.0.1');

        $this->assertTrue($result['success']);
        Http::assertSent(fn (Request $request) => str_starts_with($request->url(), 'https://sms.example.test/api/')
            && $request['mobile'] === '201012345678'
            && str_contains((string) $request['message'], '123456'));
    }

    public function test_customer_otp_is_sent_by_sms_and_never_logged_or_stored_in_plaintext(): void
    {
        Log::spy();

        $code = $this->tenant->run(fn () => app(CustomerOtpService::class)->requestOtp('01098765432'));

        Http::assertSent(fn (Request $request) => $request['mobile'] === '201098765432'
            && str_contains((string) $request['message'], $code));

        Log::shouldNotHaveReceived('info', [\Mockery::on(fn ($message) => str_contains((string) $message, $code))]);

        $logged = NotificationLog::withoutGlobalScopes()->where('event_type', 'otp_verification')->latest('id')->first();
        $this->assertNotNull($logged);
        $this->assertStringNotContainsString($code, (string) $logged->content);
    }

    public function test_affiliate_phone_otp_is_burned_after_repeated_wrong_guesses(): void
    {
        $this->tenant->run(function () {
            $affiliate = $this->makeAffiliate('+201011112222', 'BRUTE1');
            $affiliate->updateQuietly(['otp_code' => '654321', 'otp_expires_at' => now()->addMinutes(10)]);

            $service = app(AffiliateRegistrationService::class);
            for ($i = 0; $i < AffiliateRegistrationService::MAX_OTP_ATTEMPTS; $i++) {
                $this->assertFalse($service->verifyPhoneOtp($affiliate->refresh(), '000000'));
            }

            // The correct code no longer works once the attempt budget is spent
            $this->assertFalse($service->verifyPhoneOtp($affiliate->refresh(), '654321'));
            $this->assertNull($affiliate->refresh()->otp_code);
        });
    }

    public function test_affiliate_payout_otp_is_burned_after_repeated_wrong_guesses(): void
    {
        $this->tenant->run(function () {
            $affiliate = $this->makeAffiliate('+201033334444', 'BRUTE2');
            $affiliate->updateQuietly(['otp_code' => '112233', 'otp_expires_at' => now()->addMinutes(10)]);

            $service = app(AffiliatePayoutAccountService::class);
            $details = ['wallet_provider' => 'vodafone_cash', 'phone' => '01033334444'];

            for ($i = 0; $i < AffiliatePayoutAccountService::MAX_OTP_ATTEMPTS; $i++) {
                try {
                    $service->updatePayoutDetails($affiliate->refresh(), Affiliate::PAYOUT_METHOD_WALLET, $details, '999999');
                    $this->fail('Wrong OTP must be rejected.');
                } catch (InvalidArgumentException) {
                    // expected
                }
            }

            $this->expectException(InvalidArgumentException::class);
            $service->updatePayoutDetails($affiliate->refresh(), Affiliate::PAYOUT_METHOD_WALLET, $details, '112233');
        });
    }

    private function makeAffiliate(string $phone, string $code): Affiliate
    {
        /** @var Affiliate $affiliate */
        $affiliate = Affiliate::create([
            'name' => 'Brute Force Target',
            'phone' => $phone,
            'password' => 'secret123',
            'code' => $code,
            'status' => Affiliate::STATUS_ACTIVE,
            'kyc_status' => Affiliate::KYC_APPROVED,
        ]);

        return $affiliate;
    }
}
