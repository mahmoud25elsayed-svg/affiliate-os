<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Models\Affiliate;
use App\Models\ApiKey;
use App\Models\CourierAccount;
use App\Models\PayoutRequest;
use App\Models\TenantPaymentGateway;
use App\Models\User;
use App\Models\WebhookSubscription;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SecurityHeadersAndEncryptionTest extends TestCase
{
    public function test_security_headers_are_present_on_web_responses(): void
    {
        $response = $this->get('/register/merchant');

        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-XSS-Protection', '1; mode=block');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
        $this->assertStringContainsString("default-src 'self'", (string) $response->headers->get('Content-Security-Policy'));
    }

    public function test_user_two_factor_secrets_are_encrypted_at_rest_and_hidden(): void
    {
        $suffix = uniqid();

        /** @var User $user */
        $user = User::create([
            'name' => 'Sec User',
            'email' => "secuser_{$suffix}@example.com",
            'password' => 'SecurePass123!#',
            'two_factor_secret' => 'SECRET2FAKEYXYZ',
            'two_factor_recovery_codes' => ['rec-code-1', 'rec-code-2'],
        ]);

        // Raw DB inspection
        $raw = DB::table('users')->where('id', $user->id)->first();
        $this->assertNotNull($raw);
        $this->assertNotEquals('SECRET2FAKEYXYZ', $raw->two_factor_secret);
        $this->assertStringNotContainsString('SECRET2FAKEYXYZ', (string) $raw->two_factor_secret);
        $this->assertStringNotContainsString('rec-code-1', (string) $raw->two_factor_recovery_codes);

        // Model attribute decryption
        $freshUser = User::find($user->id);
        $this->assertNotNull($freshUser);
        $this->assertEquals('SECRET2FAKEYXYZ', $freshUser->two_factor_secret);
        $this->assertEquals(['rec-code-1', 'rec-code-2'], $freshUser->two_factor_recovery_codes);

        // Serialization protection
        $json = json_encode($freshUser->toArray());
        $this->assertStringNotContainsString('SECRET2FAKEYXYZ', (string) $json);
        $this->assertStringNotContainsString('rec-code-1', (string) $json);
    }

    public function test_courier_credentials_are_encrypted_at_rest_and_hidden(): void
    {
        $tenant = $this->ensureTenantExists('tenant_courier_sec');

        /** @var CourierAccount $courier */
        $courier = CourierAccount::create([
            'tenant_id' => (string) $tenant->id,
            'name' => 'Bosta Secure',
            'driver' => 'bosta',
            'api_key' => 'bosta_secret_key_12345',
            'api_secret' => 'bosta_private_secret_99999',
            'webhook_secret' => 'bosta_wh_secret_88888',
            'is_active' => true,
        ]);

        // Raw DB inspection
        $raw = DB::table('courier_accounts')->where('id', $courier->id)->first();
        $this->assertNotNull($raw);
        $this->assertNotEquals('bosta_secret_key_12345', $raw->api_key);
        $this->assertNotEquals('bosta_private_secret_99999', $raw->api_secret);
        $this->assertNotEquals('bosta_wh_secret_88888', $raw->webhook_secret);

        // Model attribute decryption
        $fresh = CourierAccount::withoutTenancy()->find($courier->id);
        $this->assertNotNull($fresh);
        $this->assertEquals('bosta_secret_key_12345', $fresh->api_key);
        $this->assertEquals('bosta_private_secret_99999', $fresh->api_secret);
        $this->assertEquals('bosta_wh_secret_88888', $fresh->webhook_secret);

        // Serialization protection
        $json = json_encode($fresh->toArray());
        $this->assertStringNotContainsString('bosta_private_secret_99999', (string) $json);
        $this->assertStringNotContainsString('bosta_wh_secret_88888', (string) $json);
    }

    public function test_webhook_secrets_are_encrypted_at_rest_and_hidden(): void
    {
        $tenant = $this->ensureTenantExists('tenant_wh_sec');

        /** @var WebhookSubscription $sub */
        $sub = WebhookSubscription::create([
            'tenant_id' => (string) $tenant->id,
            'url' => 'https://example.com/webhook',
            'secret' => 'whsec_super_secret_payload_signer',
            'events' => ['order.created'],
            'is_active' => true,
        ]);

        // Raw DB inspection
        $raw = DB::table('webhook_subscriptions')->where('id', $sub->id)->first();
        $this->assertNotNull($raw);
        $this->assertNotEquals('whsec_super_secret_payload_signer', $raw->secret);

        // Model attribute decryption
        $fresh = WebhookSubscription::withoutTenancy()->find($sub->id);
        $this->assertNotNull($fresh);
        $this->assertEquals('whsec_super_secret_payload_signer', $fresh->secret);

        // Serialization protection
        $json = json_encode($fresh->toArray());
        $this->assertStringNotContainsString('whsec_super_secret_payload_signer', (string) $json);
    }

    public function test_payout_details_are_encrypted_at_rest_and_hidden(): void
    {
        $tenant = $this->ensureTenantExists('tenant_payout_sec');
        $suffix = uniqid();

        /** @var Affiliate $affiliate */
        $affiliate = Affiliate::create([
            'tenant_id' => (string) $tenant->id,
            'name' => 'Ahmed Ali',
            'email' => "ahmed_payout_sec_{$suffix}@example.com",
            'phone' => '010'.rand(10000000, 99999999),
            'code' => 'AFF_'.rand(100000, 999999),
            'password' => 'SecurePass123!',
        ]);

        /** @var PayoutRequest $payout */
        $payout = PayoutRequest::create([
            'tenant_id' => (string) $tenant->id,
            'affiliate_id' => $affiliate->id,
            'amount_minor' => 50000,
            'fee_minor' => 500,
            'net_amount_minor' => 49500,
            'status' => PayoutRequest::STATUS_REQUESTED,
            'payment_method' => 'vodafone_cash',
            'payout_details' => [
                'phone' => '01012345678',
                'account_name' => 'Ahmed Ali',
            ],
        ]);

        // Raw DB inspection
        $raw = DB::table('payout_requests')->where('id', $payout->id)->first();
        $this->assertNotNull($raw);
        $this->assertStringNotContainsString('01012345678', (string) $raw->payout_details);

        // Model attribute decryption
        $fresh = PayoutRequest::withoutTenancy()->find($payout->id);
        $this->assertNotNull($fresh);
        $this->assertEquals('01012345678', $fresh->payout_details['phone']);

        // Serialization protection
        $json = json_encode($fresh->toArray());
        $this->assertStringNotContainsString('01012345678', (string) $json);
    }

    public function test_api_key_hash_and_payment_gateway_credentials_are_hidden(): void
    {
        $tenant = $this->ensureTenantExists('tenant_api_sec_'.uniqid());
        $keyRaw = 'plain_key_'.uniqid();
        $keyHash = hash('sha256', $keyRaw);

        /** @var ApiKey $key */
        $key = ApiKey::create([
            'tenant_id' => (string) $tenant->id,
            'name' => 'Test Key',
            'key_hash' => $keyHash,
            'key_prefix' => 'ak_live_test',
            'scopes' => ['*'],
        ]);

        $keyJson = json_encode($key->toArray());
        $this->assertStringNotContainsString($key->key_hash, (string) $keyJson);

        /** @var TenantPaymentGateway $gateway */
        $gateway = TenantPaymentGateway::create([
            'tenant_id' => (string) $tenant->id,
            'gateway' => 'paymob',
            'is_enabled' => true,
            'mode' => 'live',
            'credentials' => [
                'api_key' => 'paymob_live_api_key_xyz',
            ],
        ]);

        $gwJson = json_encode($gateway->toArray());
        $this->assertStringNotContainsString('paymob_live_api_key_xyz', (string) $gwJson);
    }
}
