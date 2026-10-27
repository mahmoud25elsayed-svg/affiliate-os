<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Domain\Catalog\Services\ProductService;
use App\Jobs\Subscriptions\ProcessPastDueSubscriptions;
use App\Jobs\Subscriptions\SuspendDelinquentSubscriptions;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Billing\Services\SubscriptionService;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Lunar\Models\Product;
use Tests\TestCase;

class SubscriptionLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PlanSeeder::class);
    }

    public function test_full_subscription_lifecycle_and_storefront_suspension_without_data_loss(): void
    {
        /** @var Plan $starterPlan */
        $starterPlan = Plan::where('slug', 'starter')->firstOrFail();

        /** @var Tenant $tenant */
        $tenant = $this->ensureTenantExists('lifecycle-store', 'lifecycle-store.affiliate.test');

        // 1. Initial Trial
        /** @var Subscription $subscription */
        $subscription = $tenant->subscription ?? $tenant->subscriptions()->create([
            'plan_id' => $starterPlan->id,
            'status' => Subscription::STATUS_TRIAL,
            'billing_cycle' => Subscription::CYCLE_MONTHLY,
            'trial_ends_at' => now()->addDays(14),
            'current_period_starts_at' => now(),
            'current_period_ends_at' => now()->addDays(14),
        ]);

        $this->assertTrue($subscription->isTrial());
        $this->assertTrue($subscription->isUsable());

        // Create some products to verify they are never lost
        $tenant->run(function () use ($tenant) {
            Product::query()->delete();
            $svc = new ProductService;
            $svc->createProduct(['name' => 'Precious Product 1'], $tenant);
            $svc->createProduct(['name' => 'Precious Product 2'], $tenant);
            $this->assertEquals(2, Product::count());
        });

        // 2. Trial expires without payment => ProcessPastDueSubscriptions
        $subscription->update([
            'trial_ends_at' => now()->subMinute(),
        ]);

        $processedCount = (new ProcessPastDueSubscriptions)->handle();
        $this->assertGreaterThanOrEqual(1, $processedCount);

        $subscription->refresh();
        $this->assertEquals(Subscription::STATUS_PAST_DUE, $subscription->status);
        $this->assertNotNull($subscription->grace_period_ends_at);
        $this->assertTrue($subscription->grace_period_ends_at->isFuture());

        // During grace period, store is still active and usable
        $this->assertTrue($subscription->isUsable());
        $this->assertEquals('active', $tenant->fresh()->status);

        // 3. Grace period expires => SuspendDelinquentSubscriptions
        $subscription->update([
            'grace_period_ends_at' => now()->subMinute(),
        ]);

        $suspendedCount = (new SuspendDelinquentSubscriptions)->handle();
        $this->assertGreaterThanOrEqual(1, $suspendedCount);

        $subscription->refresh();
        $this->assertEquals(Subscription::STATUS_SUSPENDED, $subscription->status);
        $this->assertFalse($subscription->isUsable());

        // Tenant is now suspended
        $this->assertEquals('suspended', $tenant->fresh()->status);

        // Verify products are strictly preserved!
        $tenant->run(function () {
            $this->assertEquals(2, Product::count());
        });

        // Verify storefront displays suspended page
        $response = $this->get('http://lifecycle-store.affiliate.test/storefront');

        $response->assertStatus(503);
        $response->assertSee('المتجر مغلق مؤقتًا');

        // 4. Merchant pays via ManualGateway, Admin approves => Restores storefront!
        /** @var SubscriptionService $billingService */
        $billingService = app(SubscriptionService::class);

        $invoice = $billingService->requestManualRenewal($subscription, $starterPlan, Subscription::CYCLE_MONTHLY, [
            'payment_method' => 'manual_instapay',
            'payment_reference' => 'INSTA-TEST-12345',
            'notes' => 'Renewing our subscription',
        ]);

        $this->assertTrue($invoice->isPending());

        /** @var User $admin */
        $admin = User::factory()->create([
            'is_platform_admin' => true,
        ]);

        $billingService->approveInvoice($invoice, $admin);

        $invoice->refresh();
        $this->assertTrue($invoice->isPaid());
        $this->assertEquals($admin->id, $invoice->approved_by);

        $subscription->refresh();
        $this->assertEquals(Subscription::STATUS_ACTIVE, $subscription->status);
        $this->assertTrue($subscription->isUsable());
        $this->assertTrue($subscription->current_period_ends_at->isFuture());

        // Tenant is automatically restored to active!
        $this->assertEquals('active', $tenant->fresh()->status);

        // Storefront is active again!
        $responseAfterPayment = $this->get('http://lifecycle-store.affiliate.test/storefront');

        $responseAfterPayment->assertStatus(200);
        $responseAfterPayment->assertSee('Storefront Active');

        // Verify products are still 100% intact
        $tenant->run(function () {
            $this->assertEquals(2, Product::count());
        });
    }
}
