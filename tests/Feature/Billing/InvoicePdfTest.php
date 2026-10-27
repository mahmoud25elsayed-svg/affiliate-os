<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SubscriptionInvoice;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Billing\Services\InvoicePdfGenerator;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoicePdfTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PlanSeeder::class);
    }

    public function test_can_generate_and_download_arabic_invoice_pdf(): void
    {
        /** @var Plan $proPlan */
        $proPlan = Plan::where('slug', 'pro')->firstOrFail();

        /** @var Tenant $tenant */
        $tenant = Tenant::create([
            'id' => 'invoice-store',
            'name' => 'متجر النيل للإلكترونيات',
            'status' => 'active',
        ]);

        /** @var User $owner */
        $owner = User::factory()->create([
            'email' => 'owner@nile-electronics.test',
            'tenant_id' => $tenant->id,
        ]);

        /** @var Subscription $subscription */
        $subscription = $tenant->subscriptions()->create([
            'plan_id' => $proPlan->id,
            'status' => Subscription::STATUS_ACTIVE,
            'billing_cycle' => Subscription::CYCLE_MONTHLY,
            'current_period_starts_at' => now(),
            'current_period_ends_at' => now()->addMonth(),
        ]);

        /** @var SubscriptionInvoice $invoice */
        $invoice = SubscriptionInvoice::create([
            'invoice_number' => 'INV-2026-00001',
            'subscription_id' => $subscription->id,
            'tenant_id' => $tenant->id,
            'amount' => 79900,
            'currency' => 'EGP',
            'status' => SubscriptionInvoice::STATUS_PAID,
            'billing_period_starts_at' => now(),
            'billing_period_ends_at' => now()->addMonth(),
            'due_at' => now()->addDays(3),
            'paid_at' => now(),
            'payment_method' => 'manual_instapay',
            'payment_reference' => 'INSTA-998877',
        ]);

        // 1. Direct PDF generation
        $generator = new InvoicePdfGenerator;
        $pdfBinary = $generator->generate($invoice);

        $this->assertNotEmpty($pdfBinary);
        $this->assertStringStartsWith('%PDF', $pdfBinary);

        // 2. Download endpoint authorization: Owner can download
        $response = $this->actingAs($owner)
            ->get(route('billing.invoices.download', $invoice));

        $response->assertStatus(200);
        $this->assertEquals('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('invoice-INV-2026-00001.pdf', (string) $response->headers->get('Content-Disposition'));

        // 3. Platform Admin can download
        $admin = User::factory()->create(['is_platform_admin' => true]);
        $responseAdmin = $this->actingAs($admin)
            ->get(route('billing.invoices.download', $invoice));

        $responseAdmin->assertStatus(200);

        // 4. Another tenant user gets 403 Forbidden
        $otherUser = User::factory()->create(['tenant_id' => 'other-tenant', 'is_platform_admin' => false]);
        $responseForbidden = $this->actingAs($otherUser)
            ->get(route('billing.invoices.download', $invoice));

        $responseForbidden->assertStatus(403);
    }
}
