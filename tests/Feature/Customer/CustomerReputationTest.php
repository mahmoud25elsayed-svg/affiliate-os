<?php

declare(strict_types=1);

namespace Tests\Feature\Customer;

use App\Domain\Customer\Services\CustomerReputationService;
use App\Models\Tenant;
use App\Support\Phone\EgyptianPhone;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerReputationTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PlanSeeder::class);
        $this->tenant = $this->ensureTenantExists('repstore');
    }

    public function test_customer_reputation_tracking_by_phone_hash(): void
    {
        $this->tenant->run(function () {
            $service = app(CustomerReputationService::class);
            $phone = EgyptianPhone::parse('01012345678');
            $phoneHash = $phone->hash();

            // Initial state: no record, baseline score 100
            $this->assertNull($service->getReputation($phoneHash, $this->tenant->id));
            $this->assertSame(100.0, $service->getDeliveryScore($phoneHash, $this->tenant->id));

            // Record 1st delivered order
            $rep = $service->recordDelivered($phoneHash, $this->tenant->id);
            $this->assertSame(1, $rep->delivered_orders_count);
            $this->assertSame(0, $rep->refused_orders_count);
            $this->assertSame(1, $rep->total_orders_count);
            $this->assertSame(100.0, $rep->getDeliveryRate());
            $this->assertSame('neutral', $rep->getRiskLevel()); // 1 order is neutral

            // Record 2nd delivered order
            $rep = $service->recordDelivered($phoneHash, $this->tenant->id);
            $this->assertSame(2, $rep->delivered_orders_count);
            $this->assertSame(2, $rep->total_orders_count);
            $this->assertSame('low', $rep->getRiskLevel()); // 2 delivered = low risk

            // Record 1 refused order
            $rep = $service->recordRefused($phoneHash, $this->tenant->id);
            $this->assertSame(2, $rep->delivered_orders_count);
            $this->assertSame(1, $rep->refused_orders_count);
            $this->assertSame(3, $rep->total_orders_count);

            // Delivery rate: 2/3 = 66.67%
            $this->assertSame(66.67, $rep->getDeliveryRate());
            $this->assertSame('medium', $rep->getRiskLevel()); // 66.67% is medium risk

            // Record 2 more refused orders
            $service->recordRefused($phoneHash, $this->tenant->id);
            $rep = $service->recordRefused($phoneHash, $this->tenant->id);
            $this->assertSame(2, $rep->delivered_orders_count);
            $this->assertSame(3, $rep->refused_orders_count);
            $this->assertSame(5, $rep->total_orders_count);

            // Delivery rate: 2/5 = 40.0%
            $this->assertSame(40.0, $rep->getDeliveryRate());
            $this->assertSame('high', $rep->getRiskLevel()); // Under 50% is high risk

            // Ensure no plaintext phone number is saved in reputations table
            $this->assertDatabaseHas('customer_reputations', [
                'phone_hash' => $phoneHash,
                'delivered_orders_count' => 2,
                'refused_orders_count' => 3,
            ]);
        });
    }
}
