<?php

declare(strict_types=1);

namespace Tests\Feature\Compliance;

use App\Domain\Compliance\Services\ConsentService;
use App\Domain\Compliance\Services\CustomerDataAnonymizationService;
use App\Domain\Compliance\Services\CustomerDataExportService;
use App\Models\Affiliate;
use App\Models\ApiKey;
use App\Models\ConsentLog;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\TrackingLink;
use Database\Seeders\EgyptGovernoratesSeeder;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Lunar\Base\ValueObjects\Cart\TaxBreakdown;
use Lunar\Models\Channel;
use Lunar\Models\Currency;
use Lunar\Models\Order;
use Tests\TestCase;

class ComplianceAndLegalTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PlanSeeder::class);
        $this->seed(EgyptGovernoratesSeeder::class);

        $this->tenant = Tenant::create([
            'id' => 'test-legal-tenant',
            'name' => 'Legal Test Store',
            'status' => 'active',
        ]);

        $this->tenant->domains()->create([
            'domain' => 'legal-test.localhost',
        ]);

        $proPlan = Plan::where('slug', 'pro')->first() ?? Plan::first();
        if ($proPlan) {
            Subscription::create([
                'tenant_id' => $this->tenant->id,
                'plan_id' => $proPlan->id,
                'status' => Subscription::STATUS_ACTIVE,
                'billing_cycle' => Subscription::CYCLE_MONTHLY,
                'current_period_starts_at' => now()->startOfMonth(),
                'current_period_ends_at' => now()->addMonth(),
            ]);
        }

        tenancy()->initialize($this->tenant);
    }

    protected function tearDown(): void
    {
        if (tenancy()->initialized) {
            tenancy()->end();
        }

        parent::tearDown();
    }

    public function test_all_six_legal_templates_exist_and_contain_mandatory_egyptian_legal_disclaimer(): void
    {
        $requiredTemplates = [
            'merchant-terms.md' => 'شروط وأحكام منصة AffiliateOS للتجار',
            'data-processing-agreement.md' => 'اتفاقية معالجة البيانات الشخصية',
            'affiliate-terms.md' => 'شروط وأحكام برنامج التسويق بالعمولة',
            'privacy-policy.md' => 'سياسة الخصوصية وحماية البيانات الشخصية',
            'shipping-and-returns.md' => 'سياسة الشحن والتوصيل والاستبدال والاسترجاع',
            'acceptable-use-policy.md' => 'سياسة الاستخدام المقبول ومعايير المحتوى',
        ];

        foreach ($requiredTemplates as $file => $expectedTitle) {
            $path = base_path("docs/legal/{$file}");
            $this->assertFileExists($path, "Legal document [{$file}] must exist in docs/legal/");

            $content = (string) File::get($path);
            $this->assertGreaterThan(500, strlen($content), "Legal document [{$file}] must contain substantial draft content.");
            $this->assertStringContainsString($expectedTitle, $content);

            // Mandatory Legal Disclaimer Check
            $this->assertStringContainsString('تنبيه قانوني هام', $content, "Document [{$file}] must include prominent legal disclaimer.");
            $this->assertStringContainsString('مراجعة', $content, "Document [{$file}] must state lawyer review is required.");
            $this->assertStringContainsString('151 لسنة 2020', $content, "Document [{$file}] must reference Egyptian Law 151/2020.");
        }
    }

    public function test_consent_service_records_and_checks_legal_acceptance(): void
    {
        $service = app(ConsentService::class);
        $subjectId = '01012345678';

        $this->assertFalse(
            $service->hasAccepted($this->tenant->id, ConsentLog::SUBJECT_AFFILIATE, $subjectId, ConsentLog::DOCUMENT_AFFILIATE_TERMS, '1.0')
        );

        $log = $service->recordConsent(
            tenantId: $this->tenant->id,
            subjectType: ConsentLog::SUBJECT_AFFILIATE,
            subjectIdentifier: $subjectId,
            documentType: ConsentLog::DOCUMENT_AFFILIATE_TERMS,
            version: '1.0',
            ipAddress: '197.100.20.10',
            userAgent: 'Mozilla/5.0 Test Browser'
        );

        $this->assertInstanceOf(ConsentLog::class, $log);
        $this->assertEquals($this->tenant->id, $log->tenant_id);

        $this->assertTrue(
            $service->hasAccepted($this->tenant->id, ConsentLog::SUBJECT_AFFILIATE, $subjectId, ConsentLog::DOCUMENT_AFFILIATE_TERMS, '1.0')
        );
        $this->assertFalse(
            $service->hasAccepted($this->tenant->id, ConsentLog::SUBJECT_AFFILIATE, $subjectId, ConsentLog::DOCUMENT_AFFILIATE_TERMS, '2.0')
        );

        $history = $service->getConsentHistory($this->tenant->id, $subjectId);
        $this->assertCount(1, $history);
        $this->assertEquals(ConsentLog::DOCUMENT_AFFILIATE_TERMS, $history->first()?->document_type);
    }

    public function test_customer_data_export_service_exports_profile_addresses_orders_and_consents(): void
    {
        $customer = Customer::create([
            'tenant_id' => $this->tenant->id,
            'first_name' => 'أحمد',
            'last_name' => 'علي',
            'email' => 'ahmed.'.uniqid().'@example.com',
            'phone' => '01012345678',
            'is_active' => true,
        ]);

        $address = CustomerAddress::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'name' => 'أحمد علي',
            'phone' => '01012345678',
            'governorate_id' => 1,
            'city' => 'مدينة نصر',
            'address_line' => 'شارع عباس العقاد عمارة 10',
            'is_default' => true,
        ]);

        app(ConsentService::class)->recordConsent(
            tenantId: $this->tenant->id,
            subjectType: ConsentLog::SUBJECT_CUSTOMER,
            subjectIdentifier: (string) $customer->id,
            documentType: ConsentLog::DOCUMENT_PRIVACY_POLICY,
            version: '1.0'
        );

        $exportService = app(CustomerDataExportService::class);
        $exportedData = $exportService->exportCustomerData($customer);

        $this->assertArrayHasKey('customer', $exportedData);
        $this->assertArrayHasKey('addresses', $exportedData);
        $this->assertArrayHasKey('orders', $exportedData);
        $this->assertArrayHasKey('consent_history', $exportedData);

        $this->assertEquals('أحمد', $exportedData['customer']['first_name']);
        $this->assertEquals($customer->email, $exportedData['customer']['email']);
        $this->assertCount(1, $exportedData['addresses']);
        $this->assertEquals('مدينة نصر', $exportedData['addresses'][0]['city']);
        $this->assertCount(1, $exportedData['consent_history']);
        $this->assertEquals('privacy_policy', $exportedData['consent_history'][0]['document_type']);
    }

    public function test_customer_data_anonymization_service_redacts_pii_and_preserves_financial_invariants(): void
    {
        $customer = Customer::create([
            'tenant_id' => $this->tenant->id,
            'first_name' => 'محمود',
            'last_name' => 'عثمان',
            'email' => 'mahmoud.'.uniqid().'@example.com',
            'phone' => '01122334455',
            'is_active' => true,
        ]);

        $address = CustomerAddress::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'name' => 'محمود عثمان',
            'phone' => '01122334455',
            'governorate_id' => 2,
            'city' => 'سيدي جابر',
            'address_line' => 'شارع المشير عمارة 5',
            'is_default' => true,
        ]);

        $currency = Currency::firstOrCreate(
            ['code' => 'EGP'],
            [
                'name' => 'Egyptian Pound',
                'exchange_rate' => 1.0,
                'decimal_places' => 2,
                'enabled' => true,
                'default' => true,
            ]
        );

        $channel = Channel::first() ?? Channel::create([
            'name' => 'Default',
            'handle' => 'default',
            'default' => true,
        ]);

        $order = Order::create([
            'tenant_id' => $this->tenant->id,
            'storefront_customer_id' => $customer->id,
            'channel_id' => $channel->id,
            'status' => 'placed',
            'reference' => 'ORD-ANON-'.uniqid(),
            'currency_code' => 'EGP',
            'sub_total' => 50000,
            'discount_total' => 0,
            'shipping_total' => 5000,
            'tax_breakdown' => new TaxBreakdown,
            'total' => 55000,
            'tax_total' => 0,
        ]);

        $anonymizeService = app(CustomerDataAnonymizationService::class);
        $anonymizeService->anonymizeCustomer($customer);

        // Reload customer (including trashed since anonymize soft-deletes)
        /** @var Customer $reloadedCustomer */
        $reloadedCustomer = Customer::withTrashed()->findOrFail($customer->id);

        $this->assertTrue($reloadedCustomer->trashed());
        $this->assertEquals('مستهلك', $reloadedCustomer->first_name);
        $this->assertEquals('مجهول', $reloadedCustomer->last_name);
        $this->assertStringContainsString('@deleted.local', (string) $reloadedCustomer->email);
        $this->assertEquals('+200000000000', $reloadedCustomer->phone_e164);
        $this->assertNotNull($reloadedCustomer->anonymized_at);
        $this->assertFalse($reloadedCustomer->is_active);

        // Verify address was redacted but governorate and city preserved for accounting/tax
        $reloadedAddress = CustomerAddress::findOrFail($address->id);
        $this->assertEquals('مستهلك مجهول', $reloadedAddress->name);
        $this->assertEquals('+200000000000', $reloadedAddress->phone);
        $this->assertEquals(2, $reloadedAddress->governorate_id);
        $this->assertEquals('سيدي جابر', $reloadedAddress->city);

        // Verify Order financial integrity is completely untouched
        $reloadedOrder = Order::findOrFail($order->id);
        $this->assertEquals(55000, $reloadedOrder->total->value);
        $this->assertEquals('EGP', $reloadedOrder->currency_code);
    }

    public function test_prune_expired_data_command_deletes_old_clicks_events_and_deliveries(): void
    {
        $oldDate = now()->subDays(100)->toDateTimeString();
        $recentDate = now()->subDays(2)->toDateTimeString();

        // 1. Create affiliate and tracking link
        $affiliate = Affiliate::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'مسوق امتثال',
            'phone' => '+2010'.random_int(10000000, 99999999),
            'email' => 'aff_prune_'.uniqid().'@test.com',
            'password' => bcrypt('secret123'),
            'code' => 'PRUNE'.uniqid(),
            'status' => Affiliate::STATUS_ACTIVE,
        ]);

        $trackingLink = TrackingLink::create([
            'tenant_id' => $this->tenant->id,
            'affiliate_id' => $affiliate->id,
            'code' => 'LINK'.uniqid(),
            'slug' => 'slug-'.uniqid(),
            'destination_url' => 'https://example.com',
        ]);

        // Insert affiliate clicks
        DB::table('affiliate_clicks')->insert([
            'tenant_id' => $this->tenant->id,
            'tracking_link_id' => $trackingLink->id,
            'affiliate_id' => $affiliate->id,
            'ip_hash' => 'dummy_old_ip',
            'ua_hash' => 'dummy_old_ua',
            'created_at' => $oldDate,
        ]);
        DB::table('affiliate_clicks')->insert([
            'tenant_id' => $this->tenant->id,
            'tracking_link_id' => $trackingLink->id,
            'affiliate_id' => $affiliate->id,
            'ip_hash' => 'dummy_recent_ip',
            'ua_hash' => 'dummy_recent_ua',
            'created_at' => $recentDate,
        ]);

        // 2. Insert ad conversion events (cutoff 180 days)
        $ancientDate = now()->subDays(200)->toDateTimeString();
        DB::table('ad_conversion_events')->insert([
            'tenant_id' => $this->tenant->id,
            'platform' => 'meta',
            'event_name' => 'ViewContent',
            'event_id' => 'evt_ancient_'.uniqid(),
            'payload' => json_encode(['test' => true]),
            'status' => 'sent',
            'created_at' => $ancientDate,
            'updated_at' => $ancientDate,
        ]);
        DB::table('ad_conversion_events')->insert([
            'tenant_id' => $this->tenant->id,
            'platform' => 'meta',
            'event_name' => 'ViewContent',
            'event_id' => 'evt_recent_'.uniqid(),
            'payload' => json_encode(['test' => true]),
            'status' => 'sent',
            'created_at' => $recentDate,
            'updated_at' => $recentDate,
        ]);

        // 3. Insert webhook deliveries (cutoff 30 days)
        $oldWebhookDate = now()->subDays(45)->toDateTimeString();
        DB::table('webhook_deliveries')->insert([
            'tenant_id' => $this->tenant->id,
            'event' => 'order.created',
            'payload' => json_encode(['order_id' => 1]),
            'status' => 'delivered',
            'attempt' => 1,
            'created_at' => $oldWebhookDate,
            'updated_at' => $oldWebhookDate,
        ]);
        DB::table('webhook_deliveries')->insert([
            'tenant_id' => $this->tenant->id,
            'event' => 'order.created',
            'payload' => json_encode(['order_id' => 2]),
            'status' => 'delivered',
            'attempt' => 1,
            'created_at' => $recentDate,
            'updated_at' => $recentDate,
        ]);

        // Run pruning command
        $exitCode = Artisan::call('compliance:prune-expired-data', [
            '--clicks-days' => 90,
            '--events-days' => 180,
            '--webhooks-days' => 30,
        ]);

        $this->assertEquals(0, $exitCode);

        // Verify old records were pruned and recent records remain
        $this->assertEquals(0, DB::table('affiliate_clicks')->where('created_at', $oldDate)->count());
        $this->assertEquals(1, DB::table('affiliate_clicks')->where('created_at', $recentDate)->count());

        $this->assertEquals(0, DB::table('ad_conversion_events')->where('created_at', $ancientDate)->count());
        $this->assertEquals(1, DB::table('ad_conversion_events')->where('created_at', $recentDate)->count());

        $this->assertEquals(0, DB::table('webhook_deliveries')->where('created_at', $oldWebhookDate)->count());
        $this->assertEquals(1, DB::table('webhook_deliveries')->where('created_at', $recentDate)->count());
    }

    public function test_store_customer_api_export_and_anonymize_endpoints(): void
    {
        $customer = Customer::create([
            'tenant_id' => $this->tenant->id,
            'first_name' => 'كريم',
            'last_name' => 'سامي',
            'email' => 'karim.'.uniqid().'@example.com',
            'phone' => '01234567890',
            'is_active' => true,
        ]);

        $rawKey = 'aff_live_'.bin2hex(random_bytes(24));
        ApiKey::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Compliance Test Key',
            'key_hash' => hash('sha256', $rawKey),
            'key_prefix' => substr($rawKey, 0, 12),
            'scopes' => [
                ApiKey::SCOPE_MERCHANT_CUSTOMERS_READ,
                ApiKey::SCOPE_MERCHANT_CUSTOMERS_WRITE,
            ],
            'rate_limit_per_minute' => 60,
        ]);

        // 1. Export endpoint test
        $exportResponse = $this->withHeader('X-API-Key', $rawKey)
            ->getJson("/api/v1/store/customers/{$customer->id}/export");

        $exportResponse->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.customer.first_name', 'كريم')
            ->assertJsonPath('data.customer.email', $customer->email);

        // 2. Anonymize endpoint test
        $anonymizeResponse = $this->withHeader('X-API-Key', $rawKey)
            ->postJson("/api/v1/store/customers/{$customer->id}/anonymize");

        $anonymizeResponse->assertStatus(200)
            ->assertJsonPath('status', 'success');

        $this->assertTrue(Customer::withTrashed()->findOrFail($customer->id)->trashed());
    }
}
