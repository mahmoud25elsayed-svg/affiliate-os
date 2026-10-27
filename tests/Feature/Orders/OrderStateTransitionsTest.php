<?php

declare(strict_types=1);

namespace Tests\Feature\Orders;

use App\Domain\Orders\DTOs\OrderActor;
use App\Domain\Orders\Enums\OrderStatus;
use App\Domain\Orders\Events\OrderCancelled;
use App\Domain\Orders\Events\OrderConfirmed;
use App\Domain\Orders\Exceptions\IllegalOrderStateMutationException;
use App\Domain\Orders\Exceptions\InvalidOrderTransitionException;
use App\Domain\Orders\Exceptions\ReasonCodeRequiredException;
use App\Domain\Orders\Services\OrderTransitions;
use App\Domain\Orders\Services\OrderTransitionService;
use App\Models\OrderEvent;
use App\Models\Tenant;
use App\Support\Enums\ReasonCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Lunar\Base\ValueObjects\Cart\TaxBreakdown;
use Lunar\Models\Channel;
use Lunar\Models\Currency;
use Lunar\Models\Order;
use Tests\TestCase;

class OrderStateTransitionsTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected OrderTransitionService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'id' => 'test-store',
            'name' => 'Test Store',
        ]);
        $this->tenant->domains()->create(['domain' => 'test-store.localhost']);
        tenancy()->initialize($this->tenant);

        Currency::create([
            'code' => 'EGP',
            'name' => 'Egyptian Pound',
            'exchange_rate' => 1.0,
            'decimal_places' => 2,
            'enabled' => true,
            'default' => true,
        ]);

        Channel::create([
            'name' => 'Web',
            'handle' => 'web',
            'default' => true,
            'url' => 'http://test-store.localhost',
        ]);

        $this->service = app(OrderTransitionService::class);
    }

    protected function createTestOrder(OrderStatus $status = OrderStatus::SUBMITTED): Order
    {
        $channel = Channel::firstOrFail();

        // Use transition guard to set initial status during test setup
        OrderTransitionService::$isTransitioning = true;

        try {
            /** @var Order $order */
            $order = Order::create([
                'channel_id' => $channel->id,
                'status' => $status->value,
                'reference' => 'ORD-TEST-'.bin2hex(random_bytes(3)),
                'customer_reference' => 'ORD-TEST-'.bin2hex(random_bytes(3)),
                'sub_total' => 10000,
                'total' => 10000,
                'discount_total' => 0,
                'shipping_total' => 0,
                'tax_total' => 0,
                'tax_breakdown' => new TaxBreakdown,
                'currency_code' => 'EGP',
                'placed_at' => now(),
            ]);
        } finally {
            OrderTransitionService::$isTransitioning = false;
        }

        return $order;
    }

    /**
     * Requirement: Test generating all pairs of (from, to).
     * Allowed transitions must succeed, and all other combinations must be rejected.
     */
    public function test_all_pairs_of_transitions_allowed_succeed_and_forbidden_fail(): void
    {
        $allStatuses = OrderStatus::cases();

        foreach ($allStatuses as $from) {
            foreach ($allStatuses as $to) {
                if ($from === $to) {
                    continue; // Handled by idempotency test
                }

                $order = $this->createTestOrder($from);
                $isAllowed = OrderTransitions::isAllowed($from, $to);
                $requiresReason = OrderTransitions::requiresReason($from, $to);
                $reason = $requiresReason ? ReasonCode::CUSTOMER_CHANGED_MIND : null;

                if ($isAllowed) {
                    $updated = $this->service->transition($order, $to, $reason, OrderActor::system());
                    $this->assertEquals($to->value, $updated->status);
                    $this->assertEquals($to->value, $order->fresh()->status);
                } else {
                    $caught = false;

                    try {
                        $this->service->transition($order, $to, $reason, OrderActor::system());
                    } catch (InvalidOrderTransitionException $e) {
                        $caught = true;
                        $this->assertEquals($from, $e->from);
                        $this->assertEquals($to, $e->to);
                    }

                    $this->assertTrue(
                        $caught,
                        "Expected InvalidOrderTransitionException from {$from->value} to {$to->value} but none was thrown."
                    );
                }
            }
        }
    }

    /**
     * Acceptance criterion: Delivery directly from submitted is strictly rejected.
     */
    public function test_delivery_directly_from_submitted_is_rejected(): void
    {
        $order = $this->createTestOrder(OrderStatus::SUBMITTED);

        $this->expectException(InvalidOrderTransitionException::class);
        $this->service->transition($order, OrderStatus::DELIVERED, null, OrderActor::system());
    }

    /**
     * Acceptance criterion: Duplicate transition is idempotent and does not repeat event.
     */
    public function test_duplicate_transition_does_not_repeat_event(): void
    {
        Event::fake([OrderConfirmed::class]);

        $order = $this->createTestOrder(OrderStatus::PENDING_CONFIRMATION);

        // First transition to confirmed
        $this->service->transition($order, OrderStatus::CONFIRMED, null, OrderActor::system());
        $this->assertEquals(OrderStatus::CONFIRMED->value, $order->status);

        $initialEventsCount = OrderEvent::where('order_id', $order->id)->count();
        $this->assertEquals(1, $initialEventsCount);

        Event::assertDispatched(OrderConfirmed::class, 1);

        // Duplicate transition to the same status
        $this->service->transition($order, OrderStatus::CONFIRMED, null, OrderActor::system());

        // Event count should remain identical
        $this->assertEquals($initialEventsCount, OrderEvent::where('order_id', $order->id)->count());

        // Domain event must not be dispatched a second time
        Event::assertDispatched(OrderConfirmed::class, 1);
    }

    /**
     * Test transition requiring ReasonCode fails without reason and succeeds with reason.
     */
    public function test_transition_requiring_reason_code_enforcement(): void
    {
        $order = $this->createTestOrder(OrderStatus::PENDING_CONFIRMATION);

        // Attempt cancellation without reason code
        try {
            $this->service->transition($order, OrderStatus::CANCELLED_BY_CUSTOMER, null, OrderActor::system());
            $this->fail('Expected ReasonCodeRequiredException was not thrown.');
        } catch (ReasonCodeRequiredException $e) {
            $this->assertEquals(OrderStatus::PENDING_CONFIRMATION, $e->from);
            $this->assertEquals(OrderStatus::CANCELLED_BY_CUSTOMER, $e->to);
        }

        Event::fake([OrderCancelled::class]);

        // Now attempt with valid ReasonCode
        $this->service->transition(
            $order,
            OrderStatus::CANCELLED_BY_CUSTOMER,
            ReasonCode::CUSTOMER_CHANGED_MIND,
            OrderActor::customer(1, 'Ahmed'),
            ['channel' => 'phone'],
            'العميل طلب الإلغاء عبر اتصال هاتفي'
        );

        $this->assertEquals(OrderStatus::CANCELLED_BY_CUSTOMER->value, $order->fresh()->status);

        $event = OrderEvent::where('order_id', $order->id)->latest('id')->first();
        $this->assertNotNull($event);
        $this->assertEquals(ReasonCode::CUSTOMER_CHANGED_MIND, $event->reason_code);
        $this->assertEquals('customer', $event->actor_type);
        $this->assertEquals('Ahmed', $event->actor_name);
        $this->assertEquals('العميل طلب الإلغاء عبر اتصال هاتفي', $event->note);

        Event::assertDispatched(OrderCancelled::class, 1);
    }

    /**
     * Test actor authorization: customers cannot trigger staff-only transitions.
     */
    public function test_actor_authorization_enforces_permissions(): void
    {
        $order = $this->createTestOrder(OrderStatus::PACKED);

        // Customer attempts to mark order as shipped
        $customerActor = OrderActor::customer(123, 'Hassan');

        $this->expectException(InvalidOrderTransitionException::class);
        $this->service->transition($order, OrderStatus::SHIPPED, null, $customerActor);
    }

    /**
     * Golden rule: direct mutation of Order::status outside OrderTransitionService is blocked.
     */
    public function test_direct_status_mutation_outside_service_throws_exception(): void
    {
        $order = $this->createTestOrder(OrderStatus::SUBMITTED);

        $this->expectException(IllegalOrderStateMutationException::class);

        // Attempt direct mutation
        $order->status = OrderStatus::DELIVERED->value;
        $order->save();
    }
}
