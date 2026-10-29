<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Models\Affiliate;
use App\Models\CourierAccount;
use App\Models\Domain;
use App\Models\Shipment;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Lunar\Base\ValueObjects\Cart\TaxBreakdown;
use Lunar\Models\Channel;
use Lunar\Models\Currency;
use Lunar\Models\Order;
use Tests\TestCase;

class IdorProtectionTest extends TestCase
{
    public function test_cannot_view_kyc_document_belonging_to_another_tenant(): void
    {
        Storage::fake('local');

        $tenantA = $this->ensureTenantExists('tenant_idor_a_'.uniqid());
        $tenantB = $this->ensureTenantExists('tenant_idor_b_'.uniqid());

        /** @var User $userA */
        $userA = User::create([
            'name' => 'Merchant A',
            'email' => 'merchant_a_'.uniqid().'@example.com',
            'password' => 'Password123!',
            'tenant_id' => (string) $tenantA->id,
        ]);

        /** @var Affiliate $affiliateB */
        $affiliateB = Affiliate::create([
            'tenant_id' => (string) $tenantB->id,
            'name' => 'Affiliate Beta',
            'email' => 'aff_b_'.uniqid().'@example.com',
            'phone' => '010'.rand(10000000, 99999999),
            'code' => 'AFF_B_'.rand(1000, 9999),
            'password' => 'Password123!',
            'id_card_front_path' => 'kyc/test_front.jpg',
        ]);
        Storage::disk('local')->put('kyc/test_front.jpg', 'dummy_content');

        // Initialize session for Tenant A
        tenancy()->initialize($tenantA);

        $response = $this->actingAs($userA)
            ->get("/store/affiliates/{$affiliateB->id}/kyc/front");

        // Must be rejected with 403 or 404 (due to tenant scoping)
        $this->assertContains($response->status(), [403, 404]);

        tenancy()->end();
    }

    public function test_cannot_view_or_dispatch_shipment_belonging_to_another_tenant(): void
    {
        $tenantA = $this->ensureTenantExists('tenant_ship_a_'.uniqid());
        $tenantB = $this->ensureTenantExists('tenant_ship_b_'.uniqid());

        /** @var User $userA */
        $userA = User::create([
            'name' => 'Merchant A',
            'email' => 'merchant_ship_a_'.uniqid().'@example.com',
            'password' => 'Password123!',
            'tenant_id' => (string) $tenantA->id,
        ]);

        $channel = Channel::first() ?? Channel::create(['name' => 'Default', 'handle' => 'default', 'default' => true]);
        $currency = Currency::first() ?? Currency::create(['code' => 'EGP', 'name' => 'Egyptian Pound', 'exchange_rate' => 1, 'decimal_places' => 2, 'enabled' => true, 'default' => true]);

        /** @var Order $orderB */
        $orderB = Order::create([
            'tenant_id' => (string) $tenantB->id,
            'channel_id' => $channel->id,
            'currency_code' => $currency->code,
            'status' => 'pending',
            'reference' => 'ORD-SEC-'.uniqid(),
            'sub_total' => 10000,
            'discount_total' => 0,
            'shipping_total' => 0,
            'total' => 10000,
            'tax_total' => 0,
            'tax_breakdown' => new TaxBreakdown,
        ]);

        $courierB = CourierAccount::create([
            'tenant_id' => (string) $tenantB->id,
            'name' => 'Bosta B',
            'driver' => 'bosta',
            'is_active' => true,
        ]);

        /** @var Shipment $shipmentB */
        $shipmentB = Shipment::create([
            'tenant_id' => (string) $tenantB->id,
            'order_id' => $orderB->id,
            'courier_account_id' => $courierB->id,
            'tracking_number' => 'TRK_'.uniqid(),
            'status' => 'in_transit',
        ]);

        // Access via Tenant A
        tenancy()->initialize($tenantA);

        $showResponse = $this->actingAs($userA)
            ->get("/store/shipments/{$shipmentB->id}");
        $this->assertContains($showResponse->status(), [403, 404]);

        $dispatchResponse = $this->actingAs($userA)
            ->post("/store/orders/{$orderB->id}/dispatch");
        $this->assertContains($dispatchResponse->status(), [403, 404]);

        tenancy()->end();
    }

    public function test_cannot_manage_custom_domains_of_another_tenant(): void
    {
        $tenantA = $this->ensureTenantExists('tenant_dom_a_'.uniqid());
        $tenantB = $this->ensureTenantExists('tenant_dom_b_'.uniqid());

        /** @var User $userA */
        $userA = User::create([
            'name' => 'Merchant A',
            'email' => 'merchant_dom_a_'.uniqid().'@example.com',
            'password' => 'Password123!',
            'tenant_id' => (string) $tenantA->id,
        ]);

        /** @var Domain $domainB */
        $domainB = Domain::create([
            'domain' => 'store-beta-'.uniqid().'.com',
            'tenant_id' => (string) $tenantB->id,
            'is_primary' => false,
            'is_custom' => true,
        ]);

        tenancy()->initialize($tenantA);

        $verifyResponse = $this->actingAs($userA)
            ->post("/store/domains/{$domainB->id}/verify");
        $this->assertContains($verifyResponse->status(), [403, 404]);

        $deleteResponse = $this->actingAs($userA)
            ->delete("/store/domains/{$domainB->id}");
        $this->assertContains($deleteResponse->status(), [403, 404]);

        tenancy()->end();
    }

    public function test_cannot_access_support_tickets_of_another_tenant(): void
    {
        $tenantA = $this->ensureTenantExists('tenant_tkt_a_'.uniqid());
        $tenantB = $this->ensureTenantExists('tenant_tkt_b_'.uniqid());

        /** @var User $userA */
        $userA = User::create([
            'name' => 'Merchant A',
            'email' => 'merchant_tkt_a_'.uniqid().'@example.com',
            'password' => 'Password123!',
            'tenant_id' => (string) $tenantA->id,
        ]);

        /** @var Ticket $ticketB */
        $ticketB = Ticket::create([
            'tenant_id' => (string) $tenantB->id,
            'ticket_number' => 'TCK-'.uniqid(),
            'subject' => 'Confidential Ticket Tenant B',
            'level' => Ticket::LEVEL_STORE,
            'department' => 'finance',
            'priority' => 'high',
            'status' => 'open',
        ]);

        tenancy()->initialize($tenantA);

        $response = $this->actingAs($userA)
            ->get("/store/support/tickets/{$ticketB->id}");
        $response->assertStatus(404);

        tenancy()->end();
    }

    public function test_kyc_upload_rejects_dangerous_non_whitelisted_file_types(): void
    {
        Storage::fake('local');

        $tenant = $this->ensureTenantExists('tenant_kyc_sec_'.uniqid());
        $domainName = "sec-kyc-{$tenant->id}.localhost";
        $tenant->domains()->create(['domain' => $domainName]);

        /** @var Affiliate $affiliate */
        $affiliate = Affiliate::create([
            'tenant_id' => (string) $tenant->id,
            'name' => 'Affiliate Test',
            'email' => 'aff_sec_'.uniqid().'@example.com',
            'phone' => '010'.rand(10000000, 99999999),
            'code' => 'AFF_SEC_'.rand(1000, 9999),
            'password' => 'Password123!',
        ]);

        tenancy()->initialize($tenant);

        // Attempt uploading SVG containing script
        $svgFile = UploadedFile::fake()->createWithContent('exploit.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');
        $validJpg = UploadedFile::fake()->image('back.jpg');

        $response = $this->actingAs($affiliate, 'affiliate')
            ->post("http://{$domainName}/partners/kyc", [
                'national_id_number' => '12345678901234',
                'id_card_front' => $svgFile,
                'id_card_back' => $validJpg,
            ]);

        $response->assertSessionHasErrors(['id_card_front']);

        // Attempt uploading HTML file
        $htmlFile = UploadedFile::fake()->createWithContent('exploit.html', '<html><body>Test</body></html>');
        $response2 = $this->actingAs($affiliate, 'affiliate')
            ->post("http://{$domainName}/partners/kyc", [
                'national_id_number' => '12345678901234',
                'id_card_front' => $htmlFile,
                'id_card_back' => $validJpg,
            ]);

        $response2->assertSessionHasErrors(['id_card_front']);

        tenancy()->end();
    }
}
