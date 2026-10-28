<?php

declare(strict_types=1);

namespace Tests\Feature\Operations;

use App\Domain\CallCenter\Enums\CallOutcome;
use App\Domain\CallCenter\Jobs\ReleaseExpiredOrderLocksJob;
use App\Domain\CallCenter\Services\CallAttemptService;
use App\Domain\CallCenter\Services\CallCenterQueueService;
use App\Domain\CallCenter\Services\OrderModificationService;
use App\Domain\Inventory\Actions\InboundStockAction;
use App\Domain\Orders\Enums\OrderStatus;
use App\Domain\Orders\Services\OrderTransitionService;
use App\Models\Affiliate;
use App\Models\CallAttempt;
use App\Models\OrderEvent;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WholesalePrice;
use App\Support\Billing\Exceptions\FeatureNotAvailableException;
use App\Support\Billing\Features;
use App\Support\Enums\ReasonCode;
use App\Support\Location\City;
use App\Support\Location\Governorate;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Lunar\Base\ValueObjects\Cart\TaxBreakdown;
use Lunar\FieldTypes\Text;
use Lunar\Models\Channel;
use Lunar\Models\Country;
use Lunar\Models\Currency;
use Lunar\Models\Language;
use Lunar\Models\Order;
use Lunar\Models\OrderAddress;
use Lunar\Models\OrderLine;
use Lunar\Models\Product;
use Lunar\Models\ProductType;
use Lunar\Models\ProductVariant;
use Lunar\Models\TaxClass;
use Tests\TestCase;

class CallCenterQueueAndConfirmationTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected User $agent1;

    protected User $agent2;

    protected Channel $channel;

    protected Currency $currency;

    protected Country $country;

    protected Governorate $governorate;

    protected ProductVariant $variant;

    protected Affiliate $affiliate;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PlanSeeder::class);

        $this->tenant = Tenant::create([
            'id' => 'cc-test-store',
            'name' => 'Call Center Store',
            'status' => 'active',
        ]);
        $this->tenant->domains()->create(['domain' => 'cc-test-store.localhost']);
        tenancy()->initialize($this->tenant);

        // Assign Pro plan to tenant so call_center feature is enabled by default
        /** @var Plan $proPlan */
        $proPlan = Plan::where('slug', 'pro')->firstOrFail();
        $this->tenant->subscriptions()->create([
            'plan_id' => $proPlan->id,
            'status' => Subscription::STATUS_ACTIVE,
            'billing_cycle' => Subscription::CYCLE_MONTHLY,
            'current_period_starts_at' => now(),
            'current_period_ends_at' => now()->addMonth(),
        ]);

        Language::firstOrCreate(['code' => 'ar'], [
            'name' => 'Arabic',
            'default' => true,
        ]);

        $this->country = Country::firstOrCreate(
            ['iso3' => 'EGY'],
            [
                'name' => 'Egypt',
                'iso2' => 'EG',
                'phonecode' => '+20',
                'currency' => 'EGP',
                'emoji' => '🇪🇬',
                'emoji_u' => 'U+1F1EA U+1F1EC',
            ]
        );

        $this->currency = Currency::create([
            'code' => 'EGP',
            'name' => 'Egyptian Pound',
            'exchange_rate' => 1.0,
            'decimal_places' => 2,
            'enabled' => true,
            'default' => true,
        ]);

        $this->channel = Channel::create([
            'name' => 'Web',
            'handle' => 'web',
            'default' => true,
            'url' => 'http://cc-test-store.localhost',
        ]);

        $this->governorate = Governorate::create([
            'code' => 'CAI',
            'name_ar' => 'القاهرة',
            'name_en' => 'Cairo',
            'is_active' => true,
        ]);

        City::create([
            'governorate_id' => $this->governorate->id,
            'name_ar' => 'مدينة نصر',
            'name_en' => 'Nasr City',
            'shipping_cost_minor' => 4500,
            'is_active' => true,
        ]);

        Warehouse::firstOrCreate([
            'tenant_id' => $this->tenant->id,
            'code' => 'MAIN',
        ], [
            'name' => 'المخزن الرئيسي',
            'type' => 'physical',
            'is_default' => true,
            'is_active' => true,
        ]);

        $this->agent1 = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'موظف كول سنتر 1',
            'email' => 'agent1@test.com',
            'password' => bcrypt('password'),
        ]);

        $this->agent2 = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'موظف كول سنتر 2',
            'email' => 'agent2@test.com',
            'password' => bcrypt('password'),
        ]);

        $this->affiliate = Affiliate::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'مسوق تجريبي',
            'phone' => '+201011223344',
            'email' => 'affiliate_cc@test.com',
            'password' => 'secret123',
            'code' => 'CC777',
            'status' => Affiliate::STATUS_ACTIVE,
            'level' => 'silver',
        ]);

        // Product with Wholesale Price
        $productType = ProductType::first() ?? ProductType::create(['name' => 'Standard']);
        $taxClass = TaxClass::first() ?? TaxClass::create(['name' => 'Standard']);

        /** @var Product $product */
        $product = Product::create([
            'product_type_id' => $productType->id,
            'status' => 'published',
            'attribute_data' => [
                'name' => new Text('ساعة يد رياضية ضد الماء'),
            ],
        ]);

        $this->variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'SPORT-WATCH-01',
            'tax_class_id' => $taxClass->id,
            'stock' => 50,
        ]);

        WholesalePrice::create([
            'product_variant_id' => $this->variant->id,
            'wholesale_price_minor' => 10000, // 100.00 EGP
            'min_price_minor' => 12000,
            'max_price_minor' => 30000,
            'compare_at_price_minor' => 25000,
            'status' => WholesalePrice::STATUS_ACTIVE,
        ]);

        $warehouse = Warehouse::where('tenant_id', $this->tenant->id)->firstOrFail();
        app(InboundStockAction::class)->execute($this->variant->id, 100, $warehouse->id);
    }

    protected function createPendingOrder(int $priority = 0, ?Carbon $nextAttemptAt = null): Order
    {
        OrderTransitionService::$isTransitioning = true;

        try {
            /** @var Order $order */
            $order = Order::create([
                'tenant_id' => $this->tenant->id,
                'channel_id' => $this->channel->id,
                'status' => OrderStatus::PENDING_CONFIRMATION->value,
                'reference' => 'CC-'.bin2hex(random_bytes(4)),
                'customer_reference' => 'CUST-'.bin2hex(random_bytes(3)),
                'sub_total' => 20000,
                'total' => 24500,
                'discount_total' => 0,
                'shipping_total' => 4500,
                'tax_total' => 0,
                'tax_breakdown' => new TaxBreakdown,
                'currency_code' => 'EGP',
                'placed_at' => now(),
                'confirmation_priority' => $priority,
                'next_attempt_at' => $nextAttemptAt,
                'call_attempts_count' => 0,
                'price_snapshot' => json_encode([
                    'affiliate_id' => $this->affiliate->id,
                    'variant_id' => $this->variant->id,
                    'quantity' => 1,
                    'selling_price_minor' => 20000,
                    'selling_total_minor' => 20000,
                    'wholesale_unit_minor' => 10000,
                    'wholesale_total_minor' => 10000,
                    'shipping_cost_minor' => 4500,
                    'cod_fee_minor' => 0,
                    'customer_total_minor' => 24500,
                    'affiliate_profit_minor' => 10000,
                    'governorate_id' => $this->governorate->id,
                ]),
            ]);

            OrderAddress::create([
                'order_id' => $order->id,
                'type' => 'shipping',
                'first_name' => 'أحمد',
                'last_name' => 'محمود',
                'contact_phone' => '01012345678',
                'line_one' => 'شارع عباس العقاد',
                'city' => 'مدينة نصر',
                'country_id' => $this->country->id,
            ]);

            OrderLine::create([
                'order_id' => $order->id,
                'purchasable_type' => ProductVariant::class,
                'purchasable_id' => $this->variant->id,
                'type' => 'physical',
                'identifier' => 'SPORT-WATCH-01',
                'unit_quantity' => 1,
                'quantity' => 1,
                'description' => 'ساعة يد رياضية ضد الماء',
                'unit_price' => 20000,
                'sub_total' => 20000,
                'discount_total' => 0,
                'tax_breakdown' => new TaxBreakdown,
                'tax_total' => 0,
                'total' => 20000,
            ]);

            return $order;
        } finally {
            OrderTransitionService::$isTransitioning = false;
        }
    }

    public function test_queue_ordering_prioritizes_higher_priority_and_due_time(): void
    {
        $queueService = app(CallCenterQueueService::class);

        // Order 1: low priority
        $orderLow = $this->createPendingOrder(priority: 10, nextAttemptAt: now()->subMinutes(5));
        // Order 2: high priority
        $orderHigh = $this->createPendingOrder(priority: 50, nextAttemptAt: now()->subMinutes(5));

        // When Agent 1 claims next order, Order 2 (higher priority) must be claimed first
        $claimed = $queueService->claimNextOrder($this->agent1);

        $this->assertNotNull($claimed);
        $this->assertSame($orderHigh->id, $claimed->id);
        $this->assertSame($this->agent1->id, (int) $claimed->assigned_to);
        $this->assertNotNull($claimed->locked_until);
    }

    public function test_concurrency_lock_prevents_two_agents_from_claiming_same_order(): void
    {
        $queueService = app(CallCenterQueueService::class);

        $order1 = $this->createPendingOrder(priority: 20);
        $order2 = $this->createPendingOrder(priority: 10);

        // Agent 1 claims order
        $claimedByAgent1 = $queueService->claimNextOrder($this->agent1);
        $this->assertNotNull($claimedByAgent1);
        $this->assertSame($order1->id, $claimedByAgent1->id);

        // Agent 2 concurrently claims next order -> MUST receive order2, NOT order1
        $claimedByAgent2 = $queueService->claimNextOrder($this->agent2);
        $this->assertNotNull($claimedByAgent2);
        $this->assertSame($order2->id, $claimedByAgent2->id);

        // Agent 1 calling claimNextOrder() again while holding an active lock -> returns their existing lock
        $agent1Recheck = $queueService->claimNextOrder($this->agent1);
        $this->assertSame($order1->id, $agent1Recheck->id);

        // Agent 3 (or another agent) attempts to claim -> no eligible unlocked orders available
        $agent3 = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Agent 3',
            'email' => 'agent3@test.com',
            'password' => bcrypt('password'),
        ]);
        $claimedByAgent3 = $queueService->claimNextOrder($agent3);
        $this->assertNull($claimedByAgent3);
    }

    public function test_recording_confirmed_attempt_transitions_order_to_confirmed_status(): void
    {
        $attemptService = app(CallAttemptService::class);
        $order = $this->createPendingOrder();

        $attempt = $attemptService->recordAttempt($order, $this->agent1, [
            'outcome' => CallOutcome::CONFIRMED->value,
            'notes' => 'تم تأكيد موعد الاستلام غداً بعد الظهر',
            'duration_seconds' => 45,
        ]);

        $this->assertInstanceOf(CallAttempt::class, $attempt);
        $this->assertSame(1, $attempt->attempt_number);
        $this->assertSame('confirmed', $attempt->outcome);

        $order->refresh();
        $this->assertContains($order->status, [OrderStatus::CONFIRMED->value, OrderStatus::RESERVED->value]);
        $this->assertNull($order->assigned_to);
        $this->assertNull($order->locked_until);
        $this->assertNull($order->next_attempt_at);

        $this->assertDatabaseHas('order_events', [
            'order_id' => $order->id,
            'to_status' => OrderStatus::CONFIRMED->value,
        ]);
    }

    public function test_recording_customer_cancelled_transitions_order_to_cancelled_by_customer(): void
    {
        $attemptService = app(CallAttemptService::class);
        $order = $this->createPendingOrder();

        $attemptService->recordAttempt($order, $this->agent1, [
            'outcome' => CallOutcome::CUSTOMER_CANCELLED->value,
            'notes' => 'العميل اشترى من متجر آخر وغير رأيه',
        ]);

        $order->refresh();
        $this->assertSame(OrderStatus::CANCELLED_BY_CUSTOMER->value, $order->status);

        $this->assertDatabaseHas('order_events', [
            'order_id' => $order->id,
            'to_status' => OrderStatus::CANCELLED_BY_CUSTOMER->value,
            'reason_code' => ReasonCode::CUSTOMER_CHANGED_MIND->value,
        ]);
    }

    public function test_recording_wrong_number_transitions_with_invalid_phone_reason(): void
    {
        $attemptService = app(CallAttemptService::class);
        $order = $this->createPendingOrder();

        $attemptService->recordAttempt($order, $this->agent1, [
            'outcome' => CallOutcome::WRONG_NUMBER->value,
            'notes' => 'صاحب الرقم أفاد بأنه لم يطلب أي شيء والرقم غير صحيح',
        ]);

        $order->refresh();
        $this->assertSame(OrderStatus::CANCELLED_BY_CUSTOMER->value, $order->status);

        $this->assertDatabaseHas('order_events', [
            'order_id' => $order->id,
            'to_status' => OrderStatus::CANCELLED_BY_CUSTOMER->value,
            'reason_code' => ReasonCode::INVALID_PHONE->value,
        ]);
    }

    public function test_retry_scheduling_and_time_travel_eligibility(): void
    {
        $queueService = app(CallCenterQueueService::class);
        $attemptService = app(CallAttemptService::class);

        $order = $this->createPendingOrder();

        // 1. Record first attempt as no_answer
        $attemptService->recordAttempt($order, $this->agent1, [
            'outcome' => CallOutcome::NO_ANSWER->value,
            'notes' => 'رن الهاتف ولم يرد أحد',
        ]);

        $order->refresh();
        $this->assertSame(OrderStatus::PENDING_CONFIRMATION->value, $order->status);
        $this->assertSame(1, $order->call_attempts_count);
        $this->assertNotNull($order->next_attempt_at);
        // Default first interval is 60 minutes
        $this->assertTrue(Carbon::parse((string) $order->next_attempt_at)->isFuture());

        // 2. Queue immediately returns null because next_attempt_at is in the future
        $claimedNow = $queueService->claimNextOrder($this->agent1);
        $this->assertNull($claimedNow);

        // 3. Travel 65 minutes into the future
        $this->travel(65)->minutes();

        // Now order is due and queue should return it for the next attempt
        $claimedLater = $queueService->claimNextOrder($this->agent1);
        $this->assertNotNull($claimedLater);
        $this->assertSame($order->id, $claimedLater->id);
    }

    public function test_attempt_exhaustion_transitions_to_unreachable_on_max_attempts(): void
    {
        $attemptService = app(CallAttemptService::class);
        $order = $this->createPendingOrder();

        // Attempt 1: No answer
        $attemptService->recordAttempt($order, $this->agent1, ['outcome' => CallOutcome::NO_ANSWER->value]);
        $order->refresh();
        $this->assertSame(1, $order->call_attempts_count);
        $this->assertSame(OrderStatus::PENDING_CONFIRMATION->value, $order->status);

        // Attempt 2: Busy
        $attemptService->recordAttempt($order, $this->agent1, ['outcome' => CallOutcome::BUSY->value]);
        $order->refresh();
        $this->assertSame(2, $order->call_attempts_count);
        $this->assertSame(OrderStatus::PENDING_CONFIRMATION->value, $order->status);

        // Attempt 3 (Max attempts reached)
        $attemptService->recordAttempt($order, $this->agent1, ['outcome' => CallOutcome::UNREACHABLE->value]);
        $order->refresh();
        $this->assertSame(3, $order->call_attempts_count);
        $this->assertSame(OrderStatus::UNREACHABLE->value, $order->status);

        $this->assertDatabaseHas('order_events', [
            'order_id' => $order->id,
            'to_status' => OrderStatus::UNREACHABLE->value,
            'reason_code' => ReasonCode::CUSTOMER_UNREACHABLE->value,
        ]);
    }

    public function test_order_modification_during_call_recalculates_quote_and_affiliate_profit(): void
    {
        $modService = app(OrderModificationService::class);
        $order = $this->createPendingOrder();

        // Modify quantity from 1 to 2, and update customer phone
        $result = $modService->modifyOrder($order, [
            'quantity' => 2,
            'contact_phone' => '01099998888',
        ], $this->agent1);

        $order->refresh();

        // Quantity doubled: Selling price = 200.00 * 2 = 400.00 EGP (40000 minor)
        // Wholesale = 100.00 * 2 = 200.00 EGP (20000 minor)
        // Shipping = 45.00 EGP (4500 minor)
        // COD Fee = 20.00 EGP (2000 minor)
        // Customer total = 465.00 EGP (46500 minor)
        // Affiliate profit = 400.00 - 200.00 = 200.00 EGP (20000 minor)
        $this->assertSame(40000, (int) $order->sub_total->value);
        $this->assertSame(46500, (int) $order->total->value);

        $this->assertSame(10000, $result['old_profit_minor']);
        $this->assertSame(20000, $result['new_profit_minor']);
        $this->assertSame(10000, $result['diff_profit_minor']);

        // Check timeline event logged with transparent Arabic message
        $this->assertDatabaseHas('order_events', [
            'order_id' => $order->id,
            'actor_id' => $this->agent1->id,
        ]);

        /** @var OrderEvent $event */
        $event = OrderEvent::where('order_id', $order->id)->latest('id')->first();
        $this->assertStringContainsString('تعديل الطلب أثناء مكالمة التأكيد', (string) $event->note);
        $this->assertStringContainsString('الكمية=2', (string) $event->note);
    }

    public function test_expired_locks_are_released_by_release_expired_locks_job(): void
    {
        $order = $this->createPendingOrder();
        $order->update([
            'assigned_to' => $this->agent1->id,
            'locked_until' => Carbon::now()->subMinutes(15), // expired lock
        ]);

        $job = new ReleaseExpiredOrderLocksJob;
        $releasedCount = $job->handle();

        $this->assertSame(1, $releasedCount);

        $order->refresh();
        $this->assertNull($order->assigned_to);
        $this->assertNull($order->locked_until);
    }

    public function test_web_call_center_desk_and_claim_and_attempt_flow(): void
    {
        $order = $this->createPendingOrder();

        // 1. Visit desk screen as authenticated agent
        $response = $this->actingAs($this->agent1)
            ->get('https://cc-test-store.localhost/store/call-center');

        $response->assertOk();
        $response->assertSee('مكتب تأكيد الطلبات والكول سنتر');
        $response->assertSee('سحب الطلب التالي للاتصال');

        // 2. Claim next order
        $claimResponse = $this->actingAs($this->agent1)
            ->post('https://cc-test-store.localhost/store/call-center/claim');

        $claimResponse->assertRedirect('https://cc-test-store.localhost/store/call-center');
        $claimResponse->assertSessionHas('success');

        // 3. Desk now shows the active order with calling details
        $deskWithOrder = $this->actingAs($this->agent1)
            ->get('https://cc-test-store.localhost/store/call-center');

        $deskWithOrder->assertOk();
        $deskWithOrder->assertSee('طلب #'.$order->id);
        $deskWithOrder->assertSee('تسجيل نتيجة المكالمة');

        // 4. Submit call outcome: confirmed
        $attemptResponse = $this->actingAs($this->agent1)
            ->post("https://cc-test-store.localhost/store/call-center/orders/{$order->id}/attempt", [
                'outcome' => 'confirmed',
                'notes' => 'تم التأكيد مع العميل بنجاح',
                'duration_seconds' => 50,
            ]);

        $attemptResponse->assertRedirect('https://cc-test-store.localhost/store/call-center');
        $attemptResponse->assertSessionHas('success');

        $order->refresh();
        $this->assertContains($order->status, [OrderStatus::CONFIRMED->value, OrderStatus::RESERVED->value]);

        $this->assertDatabaseHas('order_events', [
            'order_id' => $order->id,
            'to_status' => OrderStatus::CONFIRMED->value,
        ]);

        // 5. Performance page shows stats
        $perfResponse = $this->actingAs($this->agent1)
            ->get('https://cc-test-store.localhost/store/call-center/performance');

        $perfResponse->assertOk();
        $perfResponse->assertSee('تقارير أداء الكول سنتر وعينات الجودة');
        $perfResponse->assertSee('مؤكدة');
    }

    public function test_feature_flag_blocks_store_when_call_center_feature_is_disabled_in_plan(): void
    {
        // Switch tenant to Starter plan where call_center is disabled
        /** @var Plan $starterPlan */
        $starterPlan = Plan::where('slug', 'starter')->firstOrFail();
        $this->tenant->subscription->update(['plan_id' => $starterPlan->id]);

        $this->assertFalse(Features::enabled('call_center', $this->tenant));

        $this->expectException(FeatureNotAvailableException::class);

        $this->actingAs($this->agent1)
            ->withoutExceptionHandling()
            ->get('https://cc-test-store.localhost/store/call-center');
    }
}
