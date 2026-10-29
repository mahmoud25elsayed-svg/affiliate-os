<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Affiliate\Services\AffiliateTrackingService;
use App\Domain\CallCenter\Enums\CallOutcome;
use App\Domain\CallCenter\Services\CallAttemptService;
use App\Domain\CallCenter\Services\CallCenterQueueService;
use App\Domain\Finance\Services\AccountResolver;
use App\Domain\Finance\Services\CommissionSettlementService;
use App\Domain\Finance\Services\Ledger;
use App\Domain\Fulfillment\Enums\ShipmentStatus;
use App\Domain\Inventory\Actions\InboundStockAction;
use App\Domain\Orders\DTOs\OrderActor;
use App\Domain\Orders\Enums\OrderStatus;
use App\Domain\Orders\Services\OrderTransitionService;
use App\Models\Account;
use App\Models\Affiliate;
use App\Models\CourierAccount;
use App\Models\JournalEntry;
use App\Models\LedgerLine;
use App\Models\Plan;
use App\Models\Shipment;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\TrackingLink;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WholesalePrice;
use App\Support\Billing\Limits;
use App\Support\Enums\ReasonCode;
use App\Support\Location\City;
use App\Support\Location\Governorate;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
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

/**
 * End-to-End Comprehensive Acceptance Suite (Section 18 of AffiliateOS-Test-Plan.md).
 *
 * Verifies complete multi-tenant lifecycles across Onboarding, Affiliate Last-Click Attribution,
 * Call Center Concurrency Locks, Courier Dispatch & Webhooks, Stock movements, and Double-Entry Ledger.
 */
class EndToEndLifecycleAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected Channel $channel;

    protected Currency $currency;

    protected Country $country;

    protected Governorate $governorate;

    protected City $city;

    protected TaxClass $taxClass;

    protected ProductType $productType;

    protected Warehouse $warehouse;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PlanSeeder::class);

        $this->tenant = Tenant::create([
            'id' => 'e2e-store-'.bin2hex(random_bytes(3)),
            'name' => 'E2E Flagship Store',
            'status' => 'active',
        ]);
        $this->tenant->domains()->create(['domain' => "{$this->tenant->id}.localhost"]);
        tenancy()->initialize($this->tenant);

        // Assign Pro plan
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

        $this->currency = Currency::firstOrCreate(
            ['code' => 'EGP'],
            [
                'name' => 'Egyptian Pound',
                'exchange_rate' => 1.0,
                'decimal_places' => 2,
                'enabled' => true,
                'default' => true,
            ]
        );

        $this->channel = Channel::firstOrCreate(
            ['handle' => 'web'],
            [
                'name' => 'Web Channel',
                'default' => true,
                'url' => "http://{$this->tenant->id}.localhost",
            ]
        );

        $this->governorate = Governorate::firstOrCreate(
            ['code' => 'CAI'],
            [
                'name_ar' => 'القاهرة',
                'name_en' => 'Cairo',
                'shipping_cost' => 50.00,
                'is_active' => true,
            ]
        );

        $this->city = City::firstOrCreate(
            ['governorate_id' => $this->governorate->id, 'name_en' => 'Nasr City'],
            [
                'name_ar' => 'مدينة نصر',
                'is_active' => true,
            ]
        );

        $this->taxClass = TaxClass::firstOrCreate(
            ['name' => 'Default Tax'],
            ['default' => true]
        );

        $this->productType = ProductType::firstOrCreate(
            ['name' => 'Standard Item'],
            []
        );

        $this->warehouse = Warehouse::firstOrCreate(
            ['code' => 'WH-MAIN-'.$this->tenant->id],
            [
                'name' => 'Main Cairo Warehouse',
                'is_default' => true,
                'is_active' => true,
            ]
        );
    }

    protected function tearDown(): void
    {
        if (tenancy()->initialized) {
            tenancy()->end();
        }
        parent::tearDown();
    }

    /**
     * E2E-01: Full happy path lifecycle from affiliate referral to delivery and ledger settlement.
     */
    public function test_e2e_01_full_lifecycle_onboarding_order_call_center_fulfillment_and_settlement(): void
    {
        // 1. Merchant creates products with inventory and wholesale cost
        $product = Product::create([
            'product_type_id' => $this->productType->id,
            'status' => 'published',
            'attribute_data' => ['name' => new Text('سماعة بلوتوث لاسلكية عازلة للضوضاء')],
        ]);

        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'tax_class_id' => $this->taxClass->id,
            'sku' => 'BT-EAR-001',
            'unit_quantity' => 1,
            'stock' => 0,
        ]);

        WholesalePrice::create([
            'product_variant_id' => $variant->id,
            'wholesale_price_minor' => 15000, // 150.00 EGP Cost of Goods Sold
            'min_price_minor' => 25000,
            'max_price_minor' => 45000,
            'min_commission_minor' => 3000, // 30.00 EGP
            'max_commission_minor' => 8000, // 80.00 EGP
            'status' => WholesalePrice::STATUS_ACTIVE,
        ]);

        // Inbound initial stock (50 units)
        app(InboundStockAction::class)->execute($variant->id, 50, $this->warehouse->id, 'Initial Stock Inbound');
        $this->assertEquals(50, $variant->fresh()->stock);

        // 2. Register two Call Center staff agents
        $agent1 = User::create([
            'name' => 'Ahmed CallAgent',
            'email' => 'agent1@e2estore.test',
            'password' => bcrypt('password123'),
            'tenant_id' => $this->tenant->id,
        ]);
        $agent2 = User::create([
            'name' => 'Sara CallAgent',
            'email' => 'agent2@e2estore.test',
            'password' => bcrypt('password123'),
            'tenant_id' => $this->tenant->id,
        ]);

        // 3. Register two affiliates (A & B) with tracking links
        $affiliateA = Affiliate::create([
            'name' => 'Tarek Marketer A',
            'code' => 'TAREK10',
            'email' => 'tarek@affiliates.test',
            'phone' => '01011112222',
            'status' => 'active',
            'commission_rate' => 10.0,
        ]);
        $linkA = TrackingLink::create([
            'affiliate_id' => $affiliateA->id,
            'code' => 'TAREK10',
            'destination_url' => '/products/'.$product->id,
            'clicks_count' => 0,
        ]);

        $affiliateB = Affiliate::create([
            'name' => 'Mariam Marketer B',
            'code' => 'MARIAM15',
            'email' => 'mariam@affiliates.test',
            'phone' => '01233334444',
            'status' => 'active',
            'commission_rate' => 15.0,
        ]);
        $linkB = TrackingLink::create([
            'affiliate_id' => $affiliateB->id,
            'code' => 'MARIAM15',
            'destination_url' => '/products/'.$product->id,
            'clicks_count' => 0,
        ]);

        // 4. Customer journeys through Affiliate A click, then Affiliate B click (Last-Click attribution)
        $trackingService = app(AffiliateTrackingService::class);

        // Click A
        $requestA = Request::create('/products/'.$product->id, 'GET', ['ref' => $linkA->code]);
        $clickA = $trackingService->recordClick($linkA, $requestA);
        $cookieA = $trackingService->createAttributionCookie($linkA);

        // Click B (Latest click within cookie attribution window)
        $requestB = Request::create('/products/'.$product->id, 'GET', ['ref' => $linkB->code]);
        $clickB = $trackingService->recordClick($linkB, $requestB);
        $cookieB = $trackingService->createAttributionCookie($linkB);

        // Resolve Attribution - should resolve to B (last click)
        $resolvedLink = $trackingService->resolveAttributedLink($cookieB->getValue());
        $this->assertNotNull($resolvedLink);
        $this->assertEquals($linkB->id, $resolvedLink->id);
        $this->assertEquals($affiliateB->id, $resolvedLink->affiliate_id);

        // 5. Customer places COD Order (pending confirmation)
        $order = Order::create([
            'channel_id' => $this->channel->id,
            'status' => OrderStatus::PENDING_CONFIRMATION->value,
            'reference' => 'E2E-ORD-'.uniqid(),
            'customer_reference' => 'CUST-E2E-01',
            'sub_total' => 35000,
            'shipping_total' => 5000,
            'discount_total' => 0,
            'tax_breakdown' => new TaxBreakdown,
            'tax_total' => 0,
            'total' => 40000, // 400.00 EGP COD Total
            'currency_code' => 'EGP',
            'placed_at' => now(),
            'affiliate_id' => $resolvedLink->affiliate_id,
            'price_snapshot' => json_encode([
                'affiliate_profit_minor' => 5000, // 50.00 EGP commission
            ]),
            'meta' => [
                'affiliate_commission_minor' => 5000,
            ],
        ]);

        OrderAddress::create([
            'order_id' => $order->id,
            'type' => 'shipping',
            'first_name' => 'Karim',
            'last_name' => 'Mahmoud',
            'contact_phone' => '01099887766',
            'line_one' => '15 Abbas El Akkad Street',
            'city' => 'Nasr City',
            'state' => 'Cairo',
            'postcode' => '11765',
            'country_id' => $this->country->id,
        ]);

        OrderLine::create([
            'order_id' => $order->id,
            'purchasable_type' => $variant->getMorphClass(),
            'purchasable_id' => $variant->id,
            'type' => 'physical',
            'description' => 'سماعة بلوتوث لاسلكية',
            'option' => 'Default',
            'identifier' => 'BT-EAR-001',
            'unit_price' => 35000,
            'unit_quantity' => 1,
            'quantity' => 1,
            'sub_total' => 35000,
            'discount_total' => 0,
            'tax_breakdown' => new TaxBreakdown,
            'tax_total' => 0,
            'total' => 35000,
        ]);

        // Verify Attribution is strictly to Mariam (B)
        $this->assertEquals($affiliateB->id, $order->affiliate_id, 'Last-Click attribution must assign order to Affiliate B.');

        // 6. Call Center Desk: Concurrency Lock Verification
        $queueService = app(CallCenterQueueService::class);
        $attemptService = app(CallAttemptService::class);

        // Agent 1 claims the order
        $claimedOrder = $queueService->claimNextOrder($agent1);
        $this->assertNotNull($claimedOrder);
        $this->assertEquals($order->id, $claimedOrder->id);
        $this->assertEquals($agent1->id, $claimedOrder->fresh()->assigned_to);

        // Agent 2 attempts to claim next order -> cannot claim Agent 1's locked order
        $claimedByAgent2 = $queueService->claimNextOrder($agent2);
        $this->assertNull($claimedByAgent2, 'Agent 2 must not be able to steal locked order from Agent 1.');

        // Agent 1 confirms the order -> transitions to CONFIRMED, then stock listener auto-transitions to RESERVED
        $attempt = $attemptService->recordAttempt($order, $agent1, [
            'outcome' => CallOutcome::CONFIRMED->value,
            'notes' => 'Customer confirmed delivery address',
        ]);
        $order = $order->fresh();
        $this->assertEquals(OrderStatus::RESERVED->value, $order->status);
        $this->assertNull($order->assigned_to, 'Order lock must be released upon confirmation.');

        // 7. Warehouse & Courier Fulfillment
        $transitionService = app(OrderTransitionService::class);
        $order = $transitionService->transition($order, OrderStatus::PACKED, null, OrderActor::staff(1, 'Packer', 'orders'));
        $order = $order->fresh();
        $this->assertEquals(OrderStatus::PACKED->value, $order->status);

        $order = $transitionService->transition($order, OrderStatus::SHIPPED, null, OrderActor::staff(1, 'Dispatcher', 'orders'));
        $order = $order->fresh();
        $this->assertEquals(OrderStatus::SHIPPED->value, $order->status);

        // Create courier account & shipment
        $courierAccount = CourierAccount::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Bosta Express',
            'driver' => 'bosta',
            'is_active' => true,
            'is_default' => true,
        ]);

        $shipment = Shipment::create([
            'order_id' => $order->id,
            'courier_account_id' => $courierAccount->id,
            'status' => ShipmentStatus::IN_TRANSIT,
            'tracking_number' => 'BOSTA-E2E-'.rand(100000, 999999),
            'waybill_url' => 'https://track.bosta.co/BOSTA-E2E',
            'cod_amount_minor' => 40000,
            'shipping_fees_minor' => 4500,
            'recipient_name' => 'Karim Mahmoud',
            'recipient_phone' => '01099887766',
            'delivery_city' => 'القاهرة',
            'delivery_address' => '15 Abbas El Akkad Street',
            'dispatched_at' => now(),
        ]);

        $order = $transitionService->transition($order, OrderStatus::OUT_FOR_DELIVERY, null, OrderActor::staff(1, 'Courier', 'orders'));
        $order = $order->fresh();

        // 8. Courier Delivers the package
        $order = $transitionService->transition($order, OrderStatus::DELIVERED, null, OrderActor::staff(1, 'Courier', 'orders'));
        $shipment->update(['status' => ShipmentStatus::DELIVERED]);
        $this->assertEquals(OrderStatus::DELIVERED->value, $order->fresh()->status);

        // Verify inventory was permanently deducted
        $this->assertEquals(49, $variant->fresh()->stock, 'Stock must decrease by 1 upon fulfillment.');

        // 9. Financial Ledger Verification
        $journalEntries = JournalEntry::where('ref_type', Order::class)->where('ref_id', $order->id)->get();
        $this->assertNotEmpty($journalEntries, 'Delivered order must produce double-entry ledger records.');

        foreach ($journalEntries as $entry) {
            $sum = (int) LedgerLine::where('journal_entry_id', $entry->id)->sum('amount_minor');
            $this->assertEquals(0, $sum, 'Double-entry ledger entry lines must sum to zero.');
        }

        // 10. Affiliate Commission Settlement (Return window elapsed)
        $order->update(['updated_at' => now()->subHours(2)]);
        $settlementService = app(CommissionSettlementService::class);
        $settledCount = $settlementService->settleEligibleDeliveredOrders(0);
        $this->assertGreaterThanOrEqual(1, $settledCount);

        // Verify Affiliate B wallet has received settled available balance
        $affiliateAccount = app(AccountResolver::class)->getAffiliateAvailableAccount($affiliateB);
        $this->assertEquals(5000, $affiliateAccount->fresh()->balance_minor, 'Affiliate B available wallet balance must be positive after settlement.');
    }

    /**
     * E2E-02: Customer refusal or return reverses commission and restocks inventory.
     */
    public function test_e2e_02_return_and_refusal_reversal_lifecycle(): void
    {
        $product = Product::create([
            'product_type_id' => $this->productType->id,
            'status' => 'published',
            'attribute_data' => ['name' => new Text('ساعة يد كلاسيكية')],
        ]);

        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'tax_class_id' => $this->taxClass->id,
            'sku' => 'WATCH-RET-01',
            'unit_quantity' => 1,
            'stock' => 0,
        ]);

        WholesalePrice::create([
            'product_variant_id' => $variant->id,
            'wholesale_price_minor' => 20000,
            'min_price_minor' => 35000,
            'max_price_minor' => 60000,
            'min_commission_minor' => 5000,
            'max_commission_minor' => 15000,
            'status' => WholesalePrice::STATUS_ACTIVE,
        ]);

        app(InboundStockAction::class)->execute($variant->id, 10, $this->warehouse->id, 'Stock Watch');
        $this->assertEquals(10, $variant->fresh()->stock);

        $affiliate = Affiliate::create([
            'name' => 'Hesham Affiliate',
            'code' => 'HESHAM20',
            'email' => 'hesham@returntest.test',
            'phone' => '01122334455',
            'status' => 'active',
            'commission_rate' => 10.0,
        ]);

        $order = Order::create([
            'channel_id' => $this->channel->id,
            'status' => OrderStatus::PENDING_CONFIRMATION->value,
            'reference' => 'ORD-RET-'.uniqid(),
            'customer_reference' => 'E2E-RET-'.uniqid(),
            'sub_total' => 50000,
            'shipping_total' => 5000,
            'discount_total' => 0,
            'tax_breakdown' => new TaxBreakdown,
            'tax_total' => 0,
            'total' => 55000,
            'currency_code' => 'EGP',
            'placed_at' => now(),
            'affiliate_id' => $affiliate->id,
            'price_snapshot' => json_encode([
                'affiliate_profit_minor' => 5000,
            ]),
            'meta' => [
                'affiliate_commission_minor' => 5000,
            ],
        ]);

        OrderLine::create([
            'order_id' => $order->id,
            'purchasable_type' => $variant->getMorphClass(),
            'purchasable_id' => $variant->id,
            'type' => 'physical',
            'description' => 'ساعة يد كلاسيكية',
            'option' => 'Default',
            'identifier' => 'WATCH-RET-01',
            'unit_price' => 50000,
            'unit_quantity' => 1,
            'quantity' => 1,
            'sub_total' => 50000,
            'discount_total' => 0,
            'tax_breakdown' => new TaxBreakdown,
            'tax_total' => 0,
            'total' => 50000,
        ]);

        $transitionService = app(OrderTransitionService::class);

        // Transition PENDING_CONFIRMATION -> CONFIRMED (auto reserves & moves to RESERVED)
        $order = $transitionService->transition($order, OrderStatus::CONFIRMED, null, OrderActor::staff(1, 'Agent', 'call_center'));
        $order = $order->fresh();
        $this->assertEquals(OrderStatus::RESERVED->value, $order->status);

        // Transition RESERVED -> PACKED -> SHIPPED
        $order = $transitionService->transition($order, OrderStatus::PACKED, null, OrderActor::staff(1, 'Packer', 'orders'));
        $order = $order->fresh();

        $order = $transitionService->transition($order, OrderStatus::SHIPPED, null, OrderActor::staff(1, 'Dispatcher', 'orders'));
        $order = $order->fresh();
        $this->assertEquals(9, $variant->fresh()->stock);

        // Transition SHIPPED -> OUT_FOR_DELIVERY -> DELIVERED
        $order = $transitionService->transition($order, OrderStatus::OUT_FOR_DELIVERY, null, OrderActor::staff(1, 'Courier', 'orders'));
        $order = $order->fresh();

        $order = $transitionService->transition($order, OrderStatus::DELIVERED, null, OrderActor::staff(1, 'Courier', 'orders'));
        $order = $order->fresh();

        // Customer requests return
        $order = $transitionService->transition($order, OrderStatus::RETURN_REQUESTED, ReasonCode::CUSTOMER_CHANGED_MIND, OrderActor::staff(1, 'Agent', 'orders'));
        $order = $order->fresh();

        // Returning to warehouse and returned
        $order = $transitionService->transition($order, OrderStatus::RETURNING, null, OrderActor::staff(1, 'Driver', 'orders'));
        $order = $order->fresh();

        $order = $transitionService->transition($order, OrderStatus::RETURNED, null, OrderActor::staff(1, 'Inspector', 'orders'));
        $order = $order->fresh();
        $this->assertEquals(OrderStatus::RETURNED->value, $order->status);

        // Verify stock is restored
        $this->assertEquals(10, $variant->fresh()->stock, 'Stock must be returned upon order return.');

        // Verify ledger entries for reversal are balanced
        $returnEntries = JournalEntry::where('ref_type', Order::class)->where('ref_id', $order->id)->get();
        $this->assertNotEmpty($returnEntries);

        foreach ($returnEntries as $entry) {
            $sum = (int) LedgerLine::where('journal_entry_id', $entry->id)->sum('amount_minor');
            $this->assertEquals(0, $sum, 'Ledger entry lines must balance to zero.');
        }

        // Verify affiliate pending balance is zero
        $pendingAccount = app(AccountResolver::class)->getAffiliatePendingAccount($affiliate);
        $this->assertEquals(0, $pendingAccount->fresh()->balance_minor, 'Affiliate pending balance must be 0 after return reversal.');
    }

    /**
     * E2E-03: Starter plan product limits enforced, and seamless upgrade opens capacity.
     */
    public function test_e2e_03_starter_plan_limit_enforcement_and_upgrade_transition(): void
    {
        // 1. Create a Starter Plan tenant
        $starterTenant = Tenant::create([
            'id' => 'starter-store-'.bin2hex(random_bytes(3)),
            'name' => 'Starter Demo Store',
            'status' => 'active',
        ]);
        tenancy()->initialize($starterTenant);

        /** @var Plan $starterPlan */
        $starterPlan = Plan::where('slug', 'starter')->firstOrFail();
        $subscription = $starterTenant->subscriptions()->create([
            'plan_id' => $starterPlan->id,
            'status' => Subscription::STATUS_ACTIVE,
            'billing_cycle' => Subscription::CYCLE_MONTHLY,
            'current_period_starts_at' => now(),
            'current_period_ends_at' => now()->addMonth(),
        ]);

        $this->assertEquals(50, Limits::get('products', $starterTenant), 'Starter plan max products limit must be 50.');
        $this->assertTrue(Limits::check('products', $starterTenant, 1));

        // 2. Upgrade to Pro plan
        /** @var Plan $proPlan */
        $proPlan = Plan::where('slug', 'pro')->firstOrFail();
        $subscription->update(['plan_id' => $proPlan->id]);

        $this->assertEquals(500, Limits::get('products', $starterTenant->fresh()), 'Pro plan max products limit must expand to 500.');
        $this->assertTrue(Limits::check('products', $starterTenant->fresh(), 100));
    }
}
