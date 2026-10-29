<?php

declare(strict_types=1);

namespace Tests\Feature\Checkout;

use App\Domain\Checkout\Services\Gateways\FakePaymentGateway;
use App\Domain\Checkout\Services\Gateways\PaymobPaymentGateway;
use App\Domain\Checkout\Services\PaymentManager;
use App\Domain\Customer\Contracts\CustomerWalletServiceInterface;
use App\Domain\Orders\Enums\OrderStatus;
use App\Models\Customer;
use App\Models\CustomerWallet;
use App\Models\CustomerWalletTransaction;
use App\Models\PaymentTransaction;
use App\Models\Tenant;
use App\Models\TenantPaymentGateway;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Lunar\Base\ValueObjects\Cart\TaxBreakdown;
use Lunar\Models\Channel;
use Lunar\Models\Currency;
use Lunar\Models\Order;
use Tests\TestCase;

class PaymentGatewaysTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PlanSeeder::class);
        $this->tenant = $this->ensureTenantExists('paytest');
        tenancy()->initialize($this->tenant);

        Currency::firstOrCreate(
            ['code' => 'EGP'],
            ['name' => 'Egyptian Pound', 'exchange_rate' => 1.0, 'decimal_places' => 2, 'enabled' => true, 'default' => true]
        );

        Channel::firstOrCreate(
            ['handle' => 'default'],
            ['name' => 'Default Channel', 'default' => true]
        );
    }

    protected function tearDown(): void
    {
        if (function_exists('tenancy') && tenancy()->initialized) {
            tenancy()->end();
        }

        parent::tearDown();
    }

    protected function createTestOrder(string $ref = 'ORD-PAY-001', int $totalMinor = 50000, string $status = 'submitted'): Order
    {
        return Order::create([
            'channel_id' => Channel::first()->id,
            'status' => $status,
            'reference' => $ref,
            'customer_reference' => $ref,
            'sub_total' => $totalMinor,
            'discount_total' => 0,
            'shipping_total' => 0,
            'tax_breakdown' => new TaxBreakdown,
            'tax_total' => 0,
            'total' => $totalMinor,
            'currency_code' => 'EGP',
            'compare_currency_code' => 'EGP',
            'exchange_rate' => 1.0,
            'placed_at' => now(),
            'customer_phone_e164' => '+201000000001',
            'customer_phone_hash' => hash('sha256', '+201000000001'),
            'governorate_id' => 1,
            'payment_method' => 'fake',
            'tenant_id' => $this->tenant->id,
        ]);
    }

    public function test_fake_gateway_processes_payment_and_creates_pending_transaction(): void
    {
        $order = $this->createTestOrder('ORD-FAKE-01', 35000);
        $gateway = new FakePaymentGateway;

        $result = $gateway->process($order, ['method' => 'card']);

        $this->assertTrue($result->isRedirect);
        $this->assertNotNull($result->redirectUrl);
        $this->assertNotNull($result->transactionRef);
        $this->assertStringStartsWith('FAKE-TXN-', (string) $result->transactionRef);

        $this->assertDatabaseHas('payment_transactions', [
            'order_id' => $order->id,
            'gateway' => 'fake',
            'transaction_ref' => $result->transactionRef,
            'status' => PaymentTransaction::STATUS_PENDING,
            'amount_minor' => 35000,
            'currency' => 'EGP',
        ]);
    }

    public function test_paymob_concatenated_string_builds_in_exact_alphabetical_order(): void
    {
        $gateway = new PaymobPaymentGateway;

        $sampleObj = [
            'amount_cents' => 10000,
            'created_at' => '2026-10-27T10:00:00Z',
            'currency' => 'EGP',
            'error_occured' => false,
            'has_parent_transaction' => false,
            'id' => 1234567,
            'integration_id' => 99999,
            'is_3d_secure' => true,
            'is_auth' => false,
            'is_capture' => false,
            'is_refunded' => false,
            'is_standalone_payment' => true,
            'is_voided' => false,
            'order' => [
                'id' => 88888,
            ],
            'owner' => 54321,
            'pending' => false,
            'source_data' => [
                'pan' => '2345',
                'sub_type' => 'MasterCard',
                'type' => 'card',
            ],
            'success' => true,
        ];

        $concatenated = $gateway->buildPaymobConcatenatedString($sampleObj);

        // Expected concatenation of the 20 fields:
        $expected = '10000'
            .'2026-10-27T10:00:00Z'
            .'EGP'
            .'false'
            .'false'
            .'1234567'
            .'99999'
            .'true'
            .'false'
            .'false'
            .'false'
            .'true'
            .'false'
            .'88888'
            .'54321'
            .'false'
            .'2345'
            .'MasterCard'
            .'card'
            .'true';

        $this->assertSame($expected, $concatenated);
    }

    public function test_paymob_hmac_sha512_verification_succeeds_with_correct_secret(): void
    {
        $gateway = new PaymobPaymentGateway;
        $secret = 'test-paymob-hmac-secret-xyz';

        $obj = [
            'amount_cents' => 50000,
            'created_at' => '2026-10-27T12:00:00Z',
            'currency' => 'EGP',
            'error_occured' => false,
            'has_parent_transaction' => false,
            'id' => 987654,
            'integration_id' => 11111,
            'is_3d_secure' => true,
            'is_auth' => false,
            'is_capture' => false,
            'is_refunded' => false,
            'is_standalone_payment' => true,
            'is_voided' => false,
            'order' => ['id' => 55555],
            'owner' => 22222,
            'pending' => false,
            'source_data' => ['pan' => '1234', 'sub_type' => 'Visa', 'type' => 'card'],
            'success' => true,
        ];

        $concatenated = $gateway->buildPaymobConcatenatedString($obj);
        $signature = hash_hmac('sha512', $concatenated, $secret);

        $payload = ['obj' => $obj];

        $this->assertTrue($gateway->verifyWebhookSignature($payload, $signature, $secret));
    }

    public function test_paymob_hmac_sha512_verification_fails_with_tampered_payload_or_bad_secret(): void
    {
        $gateway = new PaymobPaymentGateway;
        $secret = 'correct-secret';

        $obj = [
            'amount_cents' => 50000,
            'created_at' => '2026-10-27T12:00:00Z',
            'currency' => 'EGP',
            'error_occured' => false,
            'has_parent_transaction' => false,
            'id' => 987654,
            'integration_id' => 11111,
            'is_3d_secure' => true,
            'is_auth' => false,
            'is_capture' => false,
            'is_refunded' => false,
            'is_standalone_payment' => true,
            'is_voided' => false,
            'order' => ['id' => 55555],
            'owner' => 22222,
            'pending' => false,
            'source_data' => ['pan' => '1234', 'sub_type' => 'Visa', 'type' => 'card'],
            'success' => true,
        ];

        $concatenated = $gateway->buildPaymobConcatenatedString($obj);
        $signature = hash_hmac('sha512', $concatenated, $secret);

        // 1. Wrong secret
        $this->assertFalse($gateway->verifyWebhookSignature(['obj' => $obj], $signature, 'wrong-secret'));

        // 2. Tampered amount
        $tamperedObj = $obj;
        $tamperedObj['amount_cents'] = 1000;
        $this->assertFalse($gateway->verifyWebhookSignature(['obj' => $tamperedObj], $signature, $secret));
    }

    public function test_payment_webhook_handles_successful_payment_and_advances_order(): void
    {
        $order = $this->createTestOrder('ORD-HOOK-01', 45000, OrderStatus::PENDING_CONFIRMATION->value);

        $txnRef = 'FAKE-TXN-HOOK-99';
        PaymentTransaction::create([
            'order_id' => $order->id,
            'gateway' => 'fake',
            'transaction_ref' => $txnRef,
            'status' => PaymentTransaction::STATUS_PENDING,
            'amount_minor' => 45000,
            'currency' => 'EGP',
            'payment_method' => 'card',
        ]);

        $payload = [
            'transaction_ref' => $txnRef,
            'order_reference' => $order->reference,
            'amount_minor' => 45000,
            'currency' => 'EGP',
            'payment_method' => 'card',
            'success' => true,
        ];

        $secret = 'fake-secret-key';
        $signature = hash_hmac('sha256', (string) json_encode($payload), $secret);

        $response = $this->withHeaders([
            'X-Signature' => $signature,
        ])->postJson('/api/webhooks/payments/fake', $payload);

        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'success',
            'paid' => true,
            'transaction_ref' => $txnRef,
        ]);

        // Transaction is marked paid
        $this->assertDatabaseHas('payment_transactions', [
            'transaction_ref' => $txnRef,
            'status' => PaymentTransaction::STATUS_PAID,
        ]);

        // Order is transitioned to confirmed
        $freshOrder = $order->fresh();
        $this->assertSame(OrderStatus::CONFIRMED->value, (string) $freshOrder?->status);
    }

    public function test_payment_webhook_rejects_invalid_signature(): void
    {
        $payload = [
            'transaction_ref' => 'FAKE-TXN-TAMPER',
            'order_reference' => 'ORD-TAMPER',
            'amount_minor' => 20000,
            'success' => true,
        ];

        $response = $this->withHeaders([
            'X-Signature' => 'completely-invalid-signature',
        ])->postJson('/api/webhooks/payments/fake', $payload);

        $response->assertStatus(401);
        $response->assertJson(['error' => 'Invalid webhook signature.']);
    }

    public function test_fake_gateway_is_not_available_in_production(): void
    {
        $this->app->detectEnvironment(fn () => 'production');

        $manager = new PaymentManager;

        // Its webhook secret is public, so a production fake gateway would accept forged "paid" callbacks
        $this->expectException(\InvalidArgumentException::class);
        $manager->get('fake');
    }

    public function test_payment_webhook_resolves_tenant_from_transaction_reference(): void
    {
        $payload = [
            'transaction_id' => 'NO-SUCH-TXN',
            'transaction_ref' => 'NO-SUCH-TXN',
            'success' => true,
        ];

        // Must not crash on the transaction lookup (previously queried a non-existent column)
        $this->withHeaders(['X-Signature' => 'invalid'])
            ->postJson('/api/webhooks/payments/fake', $payload)
            ->assertStatus(401);
    }

    public function test_payment_webhook_is_idempotent(): void
    {
        $order = $this->createTestOrder('ORD-IDEMP-01', 30000, OrderStatus::PENDING_CONFIRMATION->value);
        $txnRef = 'FAKE-TXN-IDEMP-01';

        $payload = [
            'transaction_ref' => $txnRef,
            'order_reference' => $order->reference,
            'amount_minor' => 30000,
            'currency' => 'EGP',
            'payment_method' => 'card',
            'success' => true,
        ];

        $secret = 'fake-secret-key';
        $signature = hash_hmac('sha256', (string) json_encode($payload), $secret);

        // First call: successfully processed
        $resp1 = $this->withHeaders(['X-Signature' => $signature])
            ->postJson('/api/webhooks/payments/fake', $payload);
        $resp1->assertStatus(200);
        $resp1->assertJson(['status' => 'success']);

        // Second call with same txnRef: returns ignored without errors or duplicate transactions
        $resp2 = $this->withHeaders(['X-Signature' => $signature])
            ->postJson('/api/webhooks/payments/fake', $payload);
        $resp2->assertStatus(200);
        $resp2->assertJson([
            'status' => 'ignored',
            'message' => 'Transaction already processed successfully.',
        ]);

        $this->assertEquals(1, PaymentTransaction::where('transaction_ref', $txnRef)->count());
    }

    public function test_tenant_payment_gateway_credentials_are_encrypted_at_rest(): void
    {
        $secretApiKey = 'live_secret_key_123456789_abcdef';
        $hmacSecret = 'super_secret_hmac_key_99999';

        TenantPaymentGateway::create([
            'tenant_id' => $this->tenant->id,
            'gateway' => 'paymob',
            'is_enabled' => true,
            'mode' => 'live',
            'credentials' => [
                'api_key' => $secretApiKey,
                'hmac_secret' => $hmacSecret,
            ],
        ]);

        // Raw SQL query directly into database table
        $rawRow = DB::table('tenant_payment_gateways')
            ->where('tenant_id', $this->tenant->id)
            ->where('gateway', 'paymob')
            ->first();

        $this->assertNotNull($rawRow);
        /** @var string $rawCredentials */
        $rawCredentials = (string) $rawRow->credentials;

        // Raw encrypted payload MUST NOT contain plaintext keys!
        $this->assertStringNotContainsString($secretApiKey, $rawCredentials);
        $this->assertStringNotContainsString($hmacSecret, $rawCredentials);

        // Eloquent model decrypts credentials seamlessly
        $model = TenantPaymentGateway::where('gateway', 'paymob')->firstOrFail();
        $this->assertSame($secretApiKey, $model->getCredential('api_key'));
        $this->assertSame($hmacSecret, $model->getCredential('hmac_secret'));
    }

    public function test_customer_wallet_credit_debit_and_balance_enforcement(): void
    {
        $customer = Customer::create([
            'tenant_id' => $this->tenant->id,
            'first_name' => 'أحمد',
            'last_name' => 'علي',
            'phone_e164' => '+201011112222',
            'phone_hash' => hash('sha256', '+201011112222'),
            'password' => bcrypt('password123'),
        ]);

        $walletService = app(CustomerWalletServiceInterface::class);
        $wallet = $walletService->getWallet($customer);

        $this->assertSame(0, $wallet->balance_minor);

        // 1. Credit 500 EGP (50,000 minor)
        $txn1 = $walletService->credit(
            customer: $customer,
            amountMinor: 50000,
            type: CustomerWalletTransaction::TYPE_REFUND,
            referenceId: 'REF-001',
            notes: 'استرجاع قيمة طلب ملغي'
        );

        $wallet->refresh();
        $this->assertSame(50000, $wallet->balance_minor);
        $this->assertSame(50000, $txn1->amount_minor);

        // 2. Debit 200 EGP (20,000 minor)
        $txn2 = $walletService->debit(
            customer: $customer,
            amountMinor: 20000,
            type: CustomerWalletTransaction::TYPE_PURCHASE,
            referenceId: 'ORD-001',
            notes: 'شراء منتجات من المتجر'
        );

        $wallet->refresh();
        $this->assertSame(30000, $wallet->balance_minor);
        $this->assertSame(-20000, $txn2->amount_minor);

        // 3. Attempting to debit more than balance must throw DomainException
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Insufficient wallet balance');

        $walletService->debit(
            customer: $customer,
            amountMinor: 40000, // Balance is only 30000
            type: CustomerWalletTransaction::TYPE_PURCHASE,
            referenceId: 'ORD-OVERDRAFT',
            notes: 'شراء بمبلغ أكبر من الرصيد'
        );
    }

    public function test_customer_wallet_transaction_is_append_only(): void
    {
        $customer = Customer::create([
            'tenant_id' => $this->tenant->id,
            'first_name' => 'محمود',
            'last_name' => 'مصطفى',
            'phone_e164' => '+201022223333',
            'phone_hash' => hash('sha256', '+201022223333'),
            'password' => bcrypt('password123'),
        ]);

        $wallet = CustomerWallet::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'balance_minor' => 10000,
            'currency' => 'EGP',
        ]);

        $txn = CustomerWalletTransaction::create([
            'tenant_id' => $this->tenant->id,
            'wallet_id' => $wallet->id,
            'type' => CustomerWalletTransaction::TYPE_DEPOSIT,
            'amount_minor' => 10000,
            'notes' => 'إيداع رصيد بالمحفظة',
        ]);

        // Attempting to update throws LogicException
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('append-only');

        $txn->amount_minor = 20000;
        $txn->save();
    }

    public function test_paymob_process_generates_iframe_url_and_pending_transaction(): void
    {
        TenantPaymentGateway::create([
            'tenant_id' => $this->tenant->id,
            'gateway' => 'paymob',
            'is_enabled' => true,
            'mode' => 'sandbox',
            'credentials' => [
                'iframe_id' => '998877',
            ],
        ]);

        $order = $this->createTestOrder('ORD-PMB-01', 60000);
        $gateway = new PaymobPaymentGateway;

        $result = $gateway->process($order, ['method' => 'card']);

        $this->assertTrue($result->isIframe);
        $this->assertNotNull($result->iframeUrl);
        $this->assertStringContainsString('998877', (string) $result->iframeUrl);
        $this->assertNotNull($result->transactionRef);
        $this->assertStringStartsWith('PMB-', (string) $result->transactionRef);

        $this->assertDatabaseHas('payment_transactions', [
            'order_id' => $order->id,
            'gateway' => 'paymob',
            'transaction_ref' => $result->transactionRef,
            'status' => PaymentTransaction::STATUS_PENDING,
            'amount_minor' => 60000,
        ]);
    }

    public function test_paymob_and_fake_refund_processing(): void
    {
        $fakeGateway = new FakePaymentGateway;
        $fakeRefund = $fakeGateway->refund('FAKE-TXN-1234', 15000, 'Customer requested cancellation');

        $this->assertTrue($fakeRefund->isSuccessful);
        $this->assertSame(15000, $fakeRefund->amountRefundedMinor);
        $this->assertNotNull($fakeRefund->refundRef);

        $paymobGateway = new PaymobPaymentGateway;
        $paymobRefund = $paymobGateway->refund('PMB-TXN-5678', 20000, 'Defective item return');

        $this->assertTrue($paymobRefund->isSuccessful);
        $this->assertSame(20000, $paymobRefund->amountRefundedMinor);
        $this->assertNotNull($paymobRefund->refundRef);
    }
}
