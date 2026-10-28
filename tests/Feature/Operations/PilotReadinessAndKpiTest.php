<?php

declare(strict_types=1);

namespace Tests\Feature\Operations;

use App\Domain\Affiliate\Actions\PriceQuoteAction;
use App\Domain\Operations\Services\StoreKpiMetricsService;
use App\Domain\Orders\Enums\OrderStatus;
use App\Models\Affiliate;
use App\Models\JournalEntry;
use App\Models\Supplier;
use App\Models\SupplierBatch;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WholesalePrice;
use App\Support\Location\Governorate;
use Database\Seeders\EgyptGovernoratesSeeder;
use Database\Seeders\PilotStagingSeeder;
use Database\Seeders\PlanSeeder;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Lunar\Base\ValueObjects\Cart\TaxBreakdown;
use Lunar\FieldTypes\Text;
use Lunar\Models\Channel;
use Lunar\Models\Currency;
use Lunar\Models\Language;
use Lunar\Models\Order;
use Lunar\Models\Product;
use Lunar\Models\ProductType;
use Lunar\Models\ProductVariant;
use Lunar\Models\TaxClass;
use Tests\TestCase;

class PilotReadinessAndKpiTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected User $owner;

    protected ProductVariant $variant;

    protected Supplier $supplier;

    protected Governorate $cairo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(EgyptGovernoratesSeeder::class);
        $this->seed(PlanSeeder::class);

        $this->cairo = Governorate::where('code', 'CAI')->firstOrFail();

        $this->tenant = Tenant::create([
            'id' => 'pilot-test-store',
            'name' => 'متجر التجربة التشغيلية',
            'status' => 'active',
        ]);

        $this->tenant->domains()->create([
            'domain' => 'pilot-test-store.localhost',
        ]);

        $this->tenant->run(function () {
            Language::firstOrCreate(['code' => 'ar'], [
                'name' => 'Arabic',
                'default' => true,
            ]);

            Channel::firstOrCreate(['handle' => 'webstore'], [
                'name' => 'Webstore',
                'default' => true,
                'url' => 'http://localhost',
            ]);

            $this->owner = User::create([
                'tenant_id' => $this->tenant->id,
                'name' => 'Store Owner',
                'email' => 'owner@pilot.local',
                'password' => bcrypt('password123'),
            ]);

            Warehouse::firstOrCreate(
                ['tenant_id' => $this->tenant->id, 'code' => 'MAIN-WH'],
                ['name' => 'المخزن الرئيسي', 'is_active' => true, 'is_default' => true]
            );

            $this->supplier = Supplier::create([
                'tenant_id' => $this->tenant->id,
                'name' => 'مورد التوريدات السريعة',
                'company_name' => 'شركة النيل للتجارة',
                'phone' => '01012345678',
                'status' => Supplier::STATUS_ACTIVE,
                'default_lead_time_days' => 2,
            ]);

            $currency = Currency::firstOrCreate(
                ['code' => 'EGP'],
                ['name' => 'Egyptian Pound', 'exchange_rate' => 1, 'decimal_places' => 2, 'default' => true, 'enabled' => true]
            );

            $type = ProductType::firstOrCreate(['name' => 'عام']);
            $taxClass = TaxClass::firstOrCreate(['name' => 'افتراضي'], ['default' => true]);

            $product = Product::create([
                'tenant_id' => $this->tenant->id,
                'product_type_id' => $type->id,
                'status' => 'published',
                'attribute_data' => [
                    'name' => new Text('سماعات برو لاسلكية'),
                ],
            ]);

            $this->variant = ProductVariant::create([
                'tenant_id' => $this->tenant->id,
                'product_id' => $product->id,
                'tax_class_id' => $taxClass->id,
                'sku' => 'EARBUD-PILOT-01',
                'unit_quantity' => 1,
                'stock' => 25,
            ]);

            $this->variant->prices()->create([
                'tenant_id' => $this->tenant->id,
                'currency_id' => $currency->id,
                'price' => 18000,
            ]);

            WholesalePrice::create([
                'tenant_id' => $this->tenant->id,
                'product_variant_id' => $this->variant->id,
                'supplier_id' => $this->supplier->id,
                'wholesale_price_minor' => 10000, // 100 EGP
                'min_price_minor' => 15000,       // 150 EGP
                'max_price_minor' => 25000,       // 250 EGP
                'min_commission_minor' => 2000,   // 20 EGP min override
                'max_commission_minor' => 10000,  // 100 EGP max override
                'status' => WholesalePrice::STATUS_ACTIVE,
            ]);
        });
    }

    public function test_price_quote_action_strictly_enforces_min_and_max_commission_guardrails(): void
    {
        $this->tenant->run(function () {
            $quoteAction = app(PriceQuoteAction::class);

            // Wholesale is 10,000. Min commission override is 2,000 (Min allowed price: 15,000).
            // Max commission override is 10,000 (Max allowed price: 20,000 because 10,000 + 10,000 = 20,000 <= 25,000).

            // 1. Valid selling price within bounds (18,000 minor = 180 EGP, 80 EGP profit)
            $quote = $quoteAction->execute(
                variant: $this->variant,
                quantity: 1,
                sellingPriceMinor: 18000,
                governorateId: $this->cairo->id
            );

            $this->assertSame(18000, $quote->sellingUnitPriceMinor);
            $this->assertSame(8000, $quote->affiliateProfitMinor);
            $this->assertSame(15000, $quote->minAllowedUnitPriceMinor);
            $this->assertSame(20000, $quote->maxAllowedUnitPriceMinor);

            // 2. Reject selling price below minimum allowed (12,000 < 15,000)
            $this->expectException(DomainException::class);
            $quoteAction->execute(
                variant: $this->variant,
                quantity: 1,
                sellingPriceMinor: 12000,
                governorateId: $this->cairo->id
            );
        });
    }

    public function test_price_quote_action_rejects_selling_price_exceeding_max_commission(): void
    {
        $this->tenant->run(function () {
            $quoteAction = app(PriceQuoteAction::class);

            // Max allowed unit price is 20,000 (100 EGP max commission over 100 EGP wholesale)
            $this->expectException(DomainException::class);
            $quoteAction->execute(
                variant: $this->variant,
                quantity: 1,
                sellingPriceMinor: 24000,
                governorateId: $this->cairo->id
            );
        });
    }

    public function test_store_kpi_metrics_service_calculates_correct_rates_and_pipeline(): void
    {
        $this->tenant->run(function () {
            $channel = Channel::firstOrFail();

            // Create a cohort of orders: 1 pending, 1 confirmed, 2 delivered, 1 returned, 1 cancelled
            $ref1 = Order::create([
                'tenant_id' => $this->tenant->id,
                'channel_id' => $channel->id,
                'currency_code' => 'EGP',
                'reference' => 'ORD-TEST-01',
                'status' => OrderStatus::PENDING_CONFIRMATION->value,
                'sub_total' => 15000,
                'shipping_total' => 0,
                'tax_total' => 0,
                'discount_total' => 0,
                'tax_breakdown' => new TaxBreakdown,
                'total' => 15000,
            ]);

            $ref2 = Order::create([
                'tenant_id' => $this->tenant->id,
                'channel_id' => $channel->id,
                'currency_code' => 'EGP',
                'reference' => 'ORD-TEST-02',
                'status' => OrderStatus::CONFIRMED->value,
                'sub_total' => 20000,
                'shipping_total' => 0,
                'tax_total' => 0,
                'discount_total' => 0,
                'tax_breakdown' => new TaxBreakdown,
                'total' => 20000,
            ]);

            $ref3 = Order::create([
                'tenant_id' => $this->tenant->id,
                'channel_id' => $channel->id,
                'currency_code' => 'EGP',
                'reference' => 'ORD-TEST-03',
                'status' => OrderStatus::DELIVERED->value,
                'sub_total' => 20500,
                'shipping_total' => 4500,
                'tax_total' => 0,
                'discount_total' => 0,
                'tax_breakdown' => new TaxBreakdown,
                'total' => 25000,
                'price_snapshot' => json_encode([
                    'affiliate_profit_minor' => 8000,
                    'shipping_cost_minor' => 4500,
                ]),
            ]);

            $ref4 = Order::create([
                'tenant_id' => $this->tenant->id,
                'channel_id' => $channel->id,
                'currency_code' => 'EGP',
                'reference' => 'ORD-TEST-04',
                'status' => OrderStatus::DELIVERED->value,
                'sub_total' => 25500,
                'shipping_total' => 4500,
                'tax_total' => 0,
                'discount_total' => 0,
                'tax_breakdown' => new TaxBreakdown,
                'total' => 30000,
                'price_snapshot' => json_encode([
                    'affiliate_profit_minor' => 10000,
                    'shipping_cost_minor' => 4500,
                ]),
            ]);

            $ref5 = Order::create([
                'tenant_id' => $this->tenant->id,
                'channel_id' => $channel->id,
                'currency_code' => 'EGP',
                'reference' => 'ORD-TEST-05',
                'status' => OrderStatus::RETURNED->value,
                'sub_total' => 20000,
                'shipping_total' => 0,
                'tax_total' => 0,
                'discount_total' => 0,
                'tax_breakdown' => new TaxBreakdown,
                'total' => 20000,
            ]);

            $ref6 = Order::create([
                'tenant_id' => $this->tenant->id,
                'channel_id' => $channel->id,
                'currency_code' => 'EGP',
                'reference' => 'ORD-TEST-06',
                'status' => OrderStatus::CANCELLED_BY_CUSTOMER->value,
                'sub_total' => 20000,
                'shipping_total' => 0,
                'tax_total' => 0,
                'discount_total' => 0,
                'tax_breakdown' => new TaxBreakdown,
                'total' => 20000,
            ]);

            $service = app(StoreKpiMetricsService::class);
            $metrics = $service->getMetrics('all');

            // Total orders: 6
            $this->assertSame(6, $metrics['total_orders']);
            $this->assertSame(1, $metrics['pending_confirmation_orders']);
            // Confirmed orders: ref2, ref3, ref4, ref5 = 4
            $this->assertSame(4, $metrics['confirmed_orders']);
            // Shipped cohort: ref3, ref4, ref5 = 3
            $this->assertSame(3, $metrics['shipped_orders']);
            // Delivered: 2
            $this->assertSame(2, $metrics['delivered_orders']);
            // Returned: 1
            $this->assertSame(1, $metrics['returned_orders']);
            // Cancelled: 1
            $this->assertSame(1, $metrics['cancelled_orders']);

            // Confirmation rate: 4/6 = 66.7%
            $this->assertSame(66.7, $metrics['confirmation_rate']);
            // Delivery rate: 2/3 = 66.7%
            $this->assertSame(66.7, $metrics['delivery_rate']);
            // Return rate: 1/3 = 33.3%
            $this->assertSame(33.3, $metrics['return_rate']);

            // Financial summary: 25,000 + 30,000 = 55,000
            $this->assertSame(55000, $metrics['gross_revenue_minor']);
            // Affiliate commission: 8,000 + 10,000 = 18,000
            $this->assertSame(18000, $metrics['affiliate_commission_minor']);
            // Shipping expense: 4,500 + 4,500 = 9,000
            $this->assertSame(9000, $metrics['shipping_expense_minor']);
            // Net margin: 55,000 - (18,000 + 9,000) = 28,000
            $this->assertSame(28000, $metrics['net_merchant_margin_minor']);
        });
    }

    public function test_merchant_can_access_kpi_dashboard_views(): void
    {
        $response = $this->actingAs($this->owner)
            ->get("https://{$this->tenant->id}.localhost/store/kpis?period=week");

        $response->assertStatus(200);
        $response->assertSee('لوحة مؤشرات الأداء التشغيلية');
        $response->assertSee('نسبة تأكيد الكول سنتر');
        $response->assertSee('نسبة التسليم الفعلي');
    }

    public function test_supplier_creation_and_supply_batch_flow_with_double_entry_ledger(): void
    {
        $this->tenant->run(function () {
            // 1. Create a supplier via controller
            $resSup = $this->actingAs($this->owner)
                ->post("https://{$this->tenant->id}.localhost/store/suppliers", [
                    'name' => 'الحاج ممدوح الشريف',
                    'company_name' => 'مؤسسة الشريف للتجارة',
                    'phone' => '01299887766',
                    'email' => 'sharif@supplier.com',
                    'default_lead_time_days' => 3,
                ]);

            $resSup->assertRedirect(route('store.suppliers.index'));

            /** @var Supplier $newSupplier */
            $newSupplier = Supplier::where('phone', '01299887766')->firstOrFail();
            $this->assertSame('الحاج ممدوح الشريف', $newSupplier->name);

            // 2. Supply Batch: 40 units at 95.00 EGP each (Total = 3,800.00 EGP = 380,000 minor)
            $initialStock = (int) ($this->variant->fresh()->stock ?? 0);

            $resBatch = $this->actingAs($this->owner)
                ->post("https://{$this->tenant->id}.localhost/store/suppliers/{$newSupplier->id}/batches", [
                    'product_variant_id' => $this->variant->id,
                    'quantity_supplied' => 40,
                    'unit_cost' => 95.00,
                    'notes' => 'وارد شحنة صيفية',
                ]);

            $resBatch->assertRedirect(route('store.suppliers.show', $newSupplier->id));

            // Stock incremented by 40
            $this->assertSame($initialStock + 40, (int) $this->variant->fresh()->stock);

            // Batch created
            $batch = SupplierBatch::where('supplier_id', $newSupplier->id)->firstOrFail();
            $this->assertSame(40, $batch->quantity_supplied);
            $this->assertSame(9500, $batch->unit_cost_minor);
            $this->assertSame(380000, $batch->total_cost_minor);

            // 3. Double-entry ledger verification
            $entry = JournalEntry::where('ref_type', SupplierBatch::class)
                ->where('ref_id', $batch->id)
                ->firstOrFail();

            $lines = $entry->lines()->with('account')->get();
            $this->assertCount(2, $lines);

            $sumAmounts = $lines->sum('amount_minor');
            $this->assertSame(0, (int) $sumAmounts, 'Double-entry ledger entry must be strictly balanced');

            // Inventory account debited +380,000
            $invLine = $lines->first(fn ($l) => $l->account->code === 'store:inventory');
            $this->assertNotNull($invLine);
            $this->assertSame(380000, $invLine->amount_minor);

            // Supplier payable account credited -380,000
            $supLine = $lines->first(fn ($l) => $l->account->code === "supplier:{$newSupplier->id}:payable");
            $this->assertNotNull($supLine);
            $this->assertSame(-380000, $supLine->amount_minor);

            // Check view rendering
            $resView = $this->actingAs($this->owner)
                ->get("https://{$this->tenant->id}.localhost/store/suppliers/{$newSupplier->id}");

            $resView->assertStatus(200);
            $resView->assertSee('الحاج ممدوح الشريف');
            $resView->assertSee('3,800.00'); // balance payable in EGP
        });
    }

    public function test_pilot_staging_seeder_creates_tenants_and_realistic_data(): void
    {
        $this->seed(PilotStagingSeeder::class);

        // Verify all 3 staging tenants exist
        $this->assertTrue(Tenant::where('id', 'cairo-electronics')->exists());
        $this->assertTrue(Tenant::where('id', 'delta-fashion')->exists());
        $this->assertTrue(Tenant::where('id', 'alex-cosmetics')->exists());

        // Verify data in cairo-electronics tenant
        $cairoTenant = Tenant::findOrFail('cairo-electronics');
        $cairoTenant->run(function () use ($cairoTenant) {
            $this->assertTrue(Supplier::where('tenant_id', $cairoTenant->id)->exists());
            $this->assertGreaterThanOrEqual(3, Product::where('tenant_id', $cairoTenant->id)->count());
            $this->assertGreaterThanOrEqual(3, Affiliate::where('tenant_id', $cairoTenant->id)->count());
            $this->assertGreaterThanOrEqual(5, Order::where('tenant_id', $cairoTenant->id)->count());

            // Verify KPI calculation runs on seeded tenant
            $metrics = app(StoreKpiMetricsService::class)->getMetrics('all');
            $this->assertGreaterThan(0, $metrics['total_orders']);
            $this->assertGreaterThanOrEqual(0.0, $metrics['confirmation_rate']);
            $this->assertGreaterThanOrEqual(0.0, $metrics['delivery_rate']);
        });
    }
}
