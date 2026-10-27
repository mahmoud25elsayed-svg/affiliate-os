<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Support\Billing\Exceptions\FeatureNotAvailableException;
use App\Support\Billing\Features;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FeaturesEnforcementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PlanSeeder::class);
    }

    public function test_feature_flags_are_strictly_enforced_by_plan(): void
    {
        /** @var Plan $starterPlan */
        $starterPlan = Plan::where('slug', 'starter')->firstOrFail();

        /** @var Tenant $tenant */
        $tenant = Tenant::create([
            'id' => 'feature-test-store',
            'name' => 'Feature Test Store',
            'status' => 'active',
        ]);

        $subscription = $tenant->subscriptions()->create([
            'plan_id' => $starterPlan->id,
            'status' => Subscription::STATUS_ACTIVE,
            'billing_cycle' => Subscription::CYCLE_MONTHLY,
            'current_period_starts_at' => now(),
            'current_period_ends_at' => now()->addMonth(),
        ]);

        // Starter plan has 'affiliate' = true, but 'ai' = false
        $this->assertTrue(Features::enabled('affiliate', $tenant));
        $this->assertFalse(Features::enabled('ai', $tenant));
        $this->assertFalse(Features::enabled('custom_domain', $tenant));

        // Ensure should succeed for affiliate
        Features::ensure('affiliate', $tenant);

        // Ensure should throw FeatureNotAvailableException for AI
        $thrown = false;

        try {
            Features::ensure('ai', $tenant);
        } catch (FeatureNotAvailableException $e) {
            $thrown = true;
            $this->assertEquals('ai', $e->feature);
            $this->assertStringContainsString('غير متاحة في باقتك الحالية', $e->getMessage());
        }

        $this->assertTrue($thrown, 'Expected FeatureNotAvailableException was not thrown.');
    }
}
