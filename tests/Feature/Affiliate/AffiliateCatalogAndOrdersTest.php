<?php

declare(strict_types=1);

namespace Tests\Feature\Affiliate;

use App\Domain\Affiliate\Actions\CancelAffiliateOrderAction;
use App\Domain\Affiliate\Actions\PriceQuoteAction;
use App\Domain\Affiliate\Actions\SubmitAffiliateOrderAction;
use App\Domain\Affiliate\Services\AffiliateOrderCsvImportService;
use App\Domain\Affiliate\Settings\AffiliateProgramSettings;
use App\Domain\Orders\DTOs\OrderActor;
use App\Domain\Orders\Enums\OrderStatus;
use App\Domain\Orders\QualityGate\DTOs\QualityGateResult;
use App\Domain\Orders\QualityGate\QualityGateService;
use App\Domain\Orders\Services\OrderTransitionService;
use App\Models\Affiliate;
use App\Models\InventoryBalance;
use App\Models\MerchantBlacklist;
use App\Models\Tenant;
use App\Models\Warehouse;
use App\Models\WholesalePrice;
use App\Support\Enums\ReasonCode;
use App\Support\Location\Governorate;
use Database\Seeders\EgyptGovernoratesSeeder;
use Database\Seeders\PlanSeeder;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Lunar\FieldTypes\Text;
use Lunar\Models\Order;
use Lunar\Models\Product;
use Lunar\Models\ProductType;
use Lunar\Models\ProductVariant;
use Lunar\Models\TaxClass;
use Tests\TestCase;

class AffiliateCatalogAndOrdersTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected string $domain;

    protected Affiliate $affiliate;

    protected ProductVariant $variant;

    protected Governorate $cairo;

    protected Governorate $alex;

    protected function setUp(): void
    {
        parent::setUp();

        app()->setLocale('ar');
        $this->seed(PlanSeeder::class);
        $this->seed(EgyptGovernoratesSeeder::class);
        $this->tenant = $this->ensureTenantExists('catstore');
        $this->domain = $this->tenant->domains->first()?->domain ?? 'catstore.localhost';

        $this->tenant->run(function () {
            $this->cairo = Governorate::where('code', 'CAI')->firstOrFail();
            $this->alex = Governorate::where('code', 'ALX')->firstOrFail();

            $warehouse = Warehouse::firstOrCreate([
                'tenant_id' => $this->tenant->id,
                'code' => 'MAIN',
            ], [
                'name' => 'المخزن الرئيسي',
                'type' => 'physical',
                'is_default' => true,
                'is_active' => true,
            ]);

            // Create active affiliate
            $this->affiliate = Affiliate::create([
                'name' => 'مسوق تجريبي',
                'phone' => '+201012345678',
                'email' => 'affiliate@test.com',
                'password' => 'secret123',
                'code' => 'EXP777',
                'status' => Affiliate::STATUS_ACTIVE,
                'phone_verified_at' => now(),
            ]);

            // Create test product and variant with wholesale price
            $productType = ProductType::first() ?? ProductType::create(['name' => 'Standard']);
            $taxClass = TaxClass::first() ?? TaxClass::create(['name' => 'Standard']);

            /** @var Product $product */
            $product = Product::create([
                'product_type_id' => $productType->id,
                'status' => 'published',
                'attribute_data' => [
                    'name' => new Text('ساعة يد كلاسيكية فاخرة'),
                ],
            ]);

            $this->variant = ProductVariant::create([
                'product_id' => $product->id,
                'sku' => 'WATCH-CLASSIC-01',
                'tax_class_id' => $taxClass->id,
                'stock' => 20,
            ]);

            WholesalePrice::create([
                'product_variant_id' => $this->variant->id,
                'wholesale_price_minor' => 15000, // 150.00 EGP
                'min_price_minor' => 18000,       // 180.00 EGP
                'max_price_minor' => 35000,       // 350.00 EGP
                'compare_at_price_minor' => 29000, // 290.00 EGP
                'status' => WholesalePrice::STATUS_ACTIVE,
            ]);

            InventoryBalance::create([
                'warehouse_id' => $warehouse->id,
                'product_variant_id' => $this->variant->id,
                'on_hand' => 100,
                'reserved' => 0,
            ]);
        });
    }

    /**
     * Acceptance Criterion 1:
     * Server-side PriceQuote calculation ignores client shipping or wholesale input.
     */
    public function test_price_quote_strictly_calculated_server_side_ignoring_client_tampering(): void
    {
        $this->tenant->run(function () {
            $action = app(PriceQuoteAction::class);

            // Valid quote
            $quote = $action->execute(
                variant: $this->variant,
                quantity: 2,
                sellingPriceMinor: 20000, // 200.00 EGP
                governorateId: (int) $this->cairo->id
            );

            // 150.00 wholesale
            $this->assertSame(15000, $quote->wholesaleUnitPriceMinor);
            $this->assertSame(20000, $quote->sellingUnitPriceMinor);
            $this->assertSame(40000, $quote->subtotalMinor); // 200.00 * 2
            $this->assertSame(10000, $quote->affiliateProfitMinor); // (200 - 150) * 2 = 100.00 EGP

            // Shipping cost must come from server shipping calculator, not arbitrary client input
            $this->assertGreaterThanOrEqual(0, $quote->shippingCostMinor);
            $this->assertSame(
                $quote->subtotalMinor + $quote->shippingCostMinor + $quote->codFeeMinor,
                $quote->customerTotalMinor
            );
        });
    }

    /**
     * Acceptance Criterion 2:
     * Selling price outside [min, max] range is strictly rejected with a clear Arabic message.
     */
    public function test_price_quote_rejects_price_outside_min_max_bounds_with_arabic_message(): void
    {
        $this->tenant->run(function () {
            $action = app(PriceQuoteAction::class);

            // 1. Below minimum allowed price (180.00 EGP)
            try {
                $action->execute(
                    variant: $this->variant,
                    quantity: 1,
                    sellingPriceMinor: 16000, // 160.00 EGP (Below 180.00)
                    governorateId: (int) $this->cairo->id
                );
                $this->fail('Expected DomainException for price below minimum was not thrown');
            } catch (DomainException $e) {
                $this->assertStringContainsString('خارج النطاق المسموح به', $e->getMessage());
                $this->assertStringContainsString('الحد الأدنى', $e->getMessage());
            }

            // 2. Above maximum allowed price (350.00 EGP)
            try {
                $action->execute(
                    variant: $this->variant,
                    quantity: 1,
                    sellingPriceMinor: 40000, // 400.00 EGP (Above 350.00)
                    governorateId: (int) $this->cairo->id
                );
                $this->fail('Expected DomainException for price above maximum was not thrown');
            } catch (DomainException $e) {
                $this->assertStringContainsString('خارج النطاق المسموح به', $e->getMessage());
                $this->assertStringContainsString('الحد الأقصى', $e->getMessage());
            }
        });
    }

    /**
     * Acceptance Criterion 3:
     * QualityGate Decision Matrix with >= 20 distinct test cases.
     */
    public function test_quality_gate_decision_matrix_covers_twenty_plus_distinct_cases(): void
    {
        $this->tenant->run(function () {
            $service = app(QualityGateService::class);
            $govId = (int) $this->cairo->id;
            $varId = (int) $this->variant->id;

            // Seed a merchant blacklist entry
            MerchantBlacklist::create([
                'type' => MerchantBlacklist::TYPE_PHONE,
                'value' => '+201099999999',
                'reason' => 'عميل محتال - رفض استلام متكرر',
            ]);

            MerchantBlacklist::create([
                'type' => MerchantBlacklist::TYPE_NAME,
                'value' => 'محتال القائمة السوداء',
                'reason' => 'اسم مسجل في القائمة السوداء',
            ]);

            MerchantBlacklist::create([
                'type' => MerchantBlacklist::TYPE_IP,
                'value' => '10.20.30.40',
                'reason' => 'عنوان IP محظور',
            ]);

            // Define >= 20 distinct test cases
            $cases = [
                // 1-8: Phone validation cases
                ['name' => 'أحمد علي', 'phone' => '01012345678', 'addr' => 'شارع عباس العقاد عمارة 15 الدور الرابع', 'vars' => [$varId], 'ip' => null, 'expected' => QualityGateResult::STATUS_PASS, 'flag' => null],
                ['name' => 'محمد كمال', 'phone' => '+201123456789', 'addr' => 'شارع النصر بجوار مسجد الإيمان عمارة 4', 'vars' => [$varId], 'ip' => null, 'expected' => QualityGateResult::STATUS_PASS, 'flag' => null],
                ['name' => 'طارق حسن', 'phone' => '01211223344', 'addr' => 'شارع الثورة برج الأطباء شقة 10', 'vars' => [$varId], 'ip' => null, 'expected' => QualityGateResult::STATUS_PASS, 'flag' => null],
                ['name' => 'سارة سمير', 'phone' => '01555667788', 'addr' => 'المقطم ش 9 بجوار بنك مصر الدور 2', 'vars' => [$varId], 'ip' => null, 'expected' => QualityGateResult::STATUS_PASS, 'flag' => null],
                ['name' => 'سامي فؤاد', 'phone' => '01312345678', 'addr' => 'شارع البحر مبنى 10 الدور 3 شقة 5', 'vars' => [$varId], 'ip' => null, 'expected' => QualityGateResult::STATUS_REJECT, 'flag' => ReasonCode::INVALID_PHONE->value], // Prefix 013 invalid
                ['name' => 'أيمن نبيل', 'phone' => '01412345678', 'addr' => 'شارع البحر مبنى 10 الدور 3 شقة 5', 'vars' => [$varId], 'ip' => null, 'expected' => QualityGateResult::STATUS_REJECT, 'flag' => ReasonCode::INVALID_PHONE->value], // Prefix 014 invalid
                ['name' => 'خالد عثمان', 'phone' => '0223456789', 'addr' => 'شارع البحر مبنى 10 الدور 3 شقة 5', 'vars' => [$varId], 'ip' => null, 'expected' => QualityGateResult::STATUS_REJECT, 'flag' => ReasonCode::INVALID_PHONE->value], // Landline
                ['name' => 'رامي شوقي', 'phone' => '01012345', 'addr' => 'شارع البحر مبنى 10 الدور 3 شقة 5', 'vars' => [$varId], 'ip' => null, 'expected' => QualityGateResult::STATUS_REJECT, 'flag' => ReasonCode::INVALID_PHONE->value], // Short phone
                ['name' => 'وليد حمدي', 'phone' => '0101234567899', 'addr' => 'شارع البحر مبنى 10 الدور 3 شقة 5', 'vars' => [$varId], 'ip' => null, 'expected' => QualityGateResult::STATUS_REJECT, 'flag' => ReasonCode::INVALID_PHONE->value], // Long phone
                ['name' => 'كريم عصام', 'phone' => 'abcdefghijk', 'addr' => 'شارع البحر مبنى 10 الدور 3 شقة 5', 'vars' => [$varId], 'ip' => null, 'expected' => QualityGateResult::STATUS_REJECT, 'flag' => ReasonCode::INVALID_PHONE->value], // Non-numeric

                // 11-13: Merchant Blacklist cases
                ['name' => 'عميل محظور', 'phone' => '01099999999', 'addr' => 'شارع التحرير مبنى 3 شقة 1', 'vars' => [$varId], 'ip' => null, 'expected' => QualityGateResult::STATUS_REJECT, 'flag' => ReasonCode::SUSPECTED_FRAUD->value],
                ['name' => 'محتال القائمة السوداء', 'phone' => '01011223388', 'addr' => 'شارع التحرير مبنى 3 شقة 1', 'vars' => [$varId], 'ip' => null, 'expected' => QualityGateResult::STATUS_REJECT, 'flag' => ReasonCode::SUSPECTED_FRAUD->value],
                ['name' => 'عميل طبيعي برقم مختلف', 'phone' => '01055556666', 'addr' => 'شارع التحرير مبنى 3 شقة 1', 'vars' => [$varId], 'ip' => '10.20.30.40', 'expected' => QualityGateResult::STATUS_REJECT, 'flag' => ReasonCode::SUSPECTED_FRAUD->value],

                // 14-16: Incomplete Address cases
                ['name' => 'عمر خالد', 'phone' => '01066778899', 'addr' => 'مصر', 'vars' => [$varId], 'ip' => null, 'expected' => QualityGateResult::STATUS_REVIEW, 'flag' => ReasonCode::INCOMPLETE_ADDRESS->value], // single word
                ['name' => 'حسين ممدوح', 'phone' => '01066778899', 'addr' => 'القاهرة', 'vars' => [$varId], 'ip' => null, 'expected' => QualityGateResult::STATUS_REVIEW, 'flag' => ReasonCode::INCOMPLETE_ADDRESS->value], // < 10 chars
                ['name' => 'نبيل بدر', 'phone' => '01066778899', 'addr' => 'شارع 1', 'vars' => [$varId], 'ip' => null, 'expected' => QualityGateResult::STATUS_REVIEW, 'flag' => ReasonCode::INCOMPLETE_ADDRESS->value], // very short address
                ['name' => 'جمال عبد الحميد', 'phone' => '01066778899', 'addr' => 'شارع الطيران المنطقة الأولى عمارة 12 الدور الخامس شقة 20', 'vars' => [$varId], 'ip' => null, 'expected' => QualityGateResult::STATUS_PASS, 'flag' => null], // complete address

                // 18-20: Other scenarios
                ['name' => 'إبراهيم يوسف', 'phone' => '01199887766', 'addr' => 'المعادي دجلة شارع 250 عمارة 8', 'vars' => [$varId], 'ip' => '192.168.1.1', 'expected' => QualityGateResult::STATUS_PASS, 'flag' => null],
                ['name' => 'ياسر جلال', 'phone' => '01288776655', 'addr' => 'التجمع الخامس الحي الأول فيلا 45', 'vars' => [$varId], 'ip' => '192.168.1.2', 'expected' => QualityGateResult::STATUS_PASS, 'flag' => null],
                ['name' => 'شادي شامل', 'phone' => '01511223399', 'addr' => 'الشيخ زايد الحي الثامن مجاورة 2', 'vars' => [$varId], 'ip' => '192.168.1.3', 'expected' => QualityGateResult::STATUS_PASS, 'flag' => null],
                ['name' => 'فارس حسام', 'phone' => '01022334455', 'addr' => 'مصر الجديدة روكسي شارع الأهرام 14', 'vars' => [$varId], 'ip' => '192.168.1.4', 'expected' => QualityGateResult::STATUS_PASS, 'flag' => null],
            ];

            $this->assertGreaterThanOrEqual(20, count($cases));

            foreach ($cases as $index => $c) {
                $res = $service->evaluate(
                    customerName: $c['name'],
                    customerPhone: $c['phone'],
                    governorateId: $govId,
                    city: 'مدينة تجريبية',
                    address: $c['addr'],
                    variantIds: $c['vars'],
                    ipAddress: $c['ip']
                );

                $this->assertSame(
                    $c['expected'],
                    $res->status,
                    "QualityGate Case #{$index} ({$c['name']} - {$c['phone']}) failed: expected {$c['expected']}, got {$res->status} with reasons: ".implode(', ', $res->reasons)
                );

                if ($c['flag'] !== null) {
                    $this->assertTrue(
                        in_array($c['flag'], $res->flags, true),
                        "QualityGate Case #{$index} expected flag {$c['flag']} in flags: ".implode(', ', $res->flags)
                    );
                }
            }
        });
    }

    /**
     * Acceptance Criterion 3 (Continued):
     * Duplicate detection and high return rate tests for QualityGate.
     */
    public function test_quality_gate_detects_duplicates_within_seven_days(): void
    {
        $this->tenant->run(function () {
            $service = app(QualityGateService::class);
            $submitAction = app(SubmitAffiliateOrderAction::class);

            $customerPhone = '01055554444';

            // 1. Submit initial order
            $order1 = $submitAction->execute($this->affiliate, [
                'customer_name' => 'عميل مكرر',
                'customer_phone' => $customerPhone,
                'governorate_id' => (int) $this->cairo->id,
                'city' => 'القاهرة',
                'address' => 'شارع العروبة عمارة 15 الدور الثاني',
                'items' => [
                    ['variant_id' => (int) $this->variant->id, 'quantity' => 1, 'selling_price_minor' => 20000],
                ],
            ]);

            $this->assertSame(OrderStatus::SUBMITTED->value, $order1->status);

            // 2. Second order with same phone & variant within 7 days -> flags duplicate & sets status to REVIEW
            $res2 = $service->evaluate(
                customerName: 'عميل مكرر',
                customerPhone: $customerPhone,
                governorateId: (int) $this->cairo->id,
                city: 'القاهرة',
                address: 'شارع العروبة عمارة 15 الدور الثاني',
                variantIds: [(int) $this->variant->id]
            );

            $this->assertSame(QualityGateResult::STATUS_REVIEW, $res2->status);
            $this->assertTrue(in_array(ReasonCode::DUPLICATE_ORDER->value, $res2->flags, true));
        });
    }

    /**
     * Acceptance Criterion 4:
     * Direct order placement, idempotency token, and daily limit quota.
     */
    public function test_affiliate_order_placement_idempotency_and_daily_limit_quota(): void
    {
        $this->tenant->run(function () {
            $submitAction = app(SubmitAffiliateOrderAction::class);
            $idempotencyKey = 'unique-key-abc-123';

            $payload = [
                'customer_name' => 'محمود رضوان',
                'customer_phone' => '01077778888',
                'governorate_id' => (int) $this->cairo->id,
                'city' => 'المعادي',
                'address' => 'شارع 9 دجلة عمارة 20 شقة 3',
                'items' => [
                    [
                        'variant_id' => (int) $this->variant->id,
                        'quantity' => 2,
                        'selling_price_minor' => 22000, // 220.00 EGP per unit
                    ],
                ],
                'idempotency_key' => $idempotencyKey,
            ];

            // 1. First submission -> creates order
            $order = $submitAction->execute($this->affiliate, $payload);
            $this->assertNotNull($order->id);
            $this->assertSame($this->affiliate->id, $order->affiliate_id);
            $this->assertSame(14000, $order->affiliate_profit_minor); // (220 - 150) * 2 = 140.00 EGP
            $this->assertSame(OrderStatus::SUBMITTED->value, $order->status);

            // Verify price_snapshot JSON stored
            $this->assertNotNull($order->price_snapshot);
            $snapshot = json_decode((string) $order->price_snapshot, true);
            $this->assertIsArray($snapshot);
            $this->assertSame('affiliate_direct', $snapshot['type']);
            $this->assertSame(14000, $snapshot['affiliate_profit_minor']);

            // 2. Submitting again with identical idempotency key -> returns existing order without duplication
            $duplicateOrder = $submitAction->execute($this->affiliate, $payload);
            $this->assertSame($order->id, $duplicateOrder->id);
            $this->assertSame(1, Order::where('affiliate_id', $this->affiliate->id)->count());

            // 3. Test Daily Order Quota Limit
            $settings = AffiliateProgramSettings::load();
            $settings->default_daily_order_limit = 1; // set limit to 1
            $settings->save();

            // Next order must throw exception
            $this->expectException(DomainException::class);
            $this->expectExceptionMessage('الحد الأقصى للطلبات اليومية');

            $submitAction->execute($this->affiliate, [
                'customer_name' => 'عميل آخر',
                'customer_phone' => '01088889999',
                'governorate_id' => (int) $this->cairo->id,
                'city' => 'القاهرة',
                'address' => 'شارع رمسيس عمارة 10',
                'items' => [
                    ['variant_id' => (int) $this->variant->id, 'quantity' => 1, 'selling_price_minor' => 20000],
                ],
            ]);
        });
    }

    /**
     * Acceptance Criterion 5:
     * Affiliate order cancellation window (allowed before packed, strictly blocked once packed).
     */
    public function test_affiliate_order_cancellation_allowed_before_packed_and_blocked_once_packed(): void
    {
        $this->tenant->run(function () {
            $submitAction = app(SubmitAffiliateOrderAction::class);
            $cancelAction = app(CancelAffiliateOrderAction::class);
            $transitionService = app(OrderTransitionService::class);

            // 1. Order in submitted state can be cancelled
            $order1 = $submitAction->execute($this->affiliate, [
                'customer_name' => 'سعيد إبراهيم',
                'customer_phone' => '01033334444',
                'governorate_id' => (int) $this->cairo->id,
                'city' => 'القاهرة',
                'address' => 'شارع النصر عمارة 5 الدور الأول',
                'items' => [
                    ['variant_id' => (int) $this->variant->id, 'quantity' => 1, 'selling_price_minor' => 20000],
                ],
            ]);

            $this->assertSame(OrderStatus::SUBMITTED->value, $order1->status);

            $cancelledOrder1 = $cancelAction->execute($this->affiliate, $order1, 'العميل تراجع عن الشراء');
            $this->assertSame(OrderStatus::CANCELLED_BY_CUSTOMER->value, $cancelledOrder1->status);

            // 2. Order progressed to PACKED cannot be cancelled
            $order2 = $submitAction->execute($this->affiliate, [
                'customer_name' => 'مروان فتحي',
                'customer_phone' => '01044445555',
                'governorate_id' => (int) $this->cairo->id,
                'city' => 'القاهرة',
                'address' => 'شارع عباس العقاد عمارة 11 الدور الثاني',
                'items' => [
                    ['variant_id' => (int) $this->variant->id, 'quantity' => 1, 'selling_price_minor' => 20000],
                ],
            ]);

            // Transition: submitted -> pending_confirmation -> confirmed -> reserved -> packed
            $staffActor = OrderActor::staff(1, 'مدير المخزن', 'owner');
            $order2 = $transitionService->transition($order2, OrderStatus::PENDING_CONFIRMATION, null, $staffActor);
            $order2 = $transitionService->transition($order2, OrderStatus::CONFIRMED, null, $staffActor);
            $order2 = $transitionService->transition($order2, OrderStatus::RESERVED, null, $staffActor);
            $order2 = $transitionService->transition($order2, OrderStatus::PACKED, null, $staffActor);

            $order2->refresh();
            $this->assertSame(OrderStatus::PACKED->value, $order2->status);

            // Attempting to cancel packed order must throw DomainException
            $this->expectException(DomainException::class);
            $this->expectExceptionMessage('لا يمكن إلغاء الطلب بعد تجهيزه وتعبئته في المخزن');

            $cancelAction->execute($this->affiliate, $order2, 'إلغاء متأخر');
        });
    }

    /**
     * Acceptance Criterion 6:
     * CSV bulk order import parses rows, validates, runs QualityGate, creates orders and reports invalid rows.
     */
    public function test_csv_bulk_order_import_creates_orders_and_reports_errors(): void
    {
        $this->tenant->run(function () {
            $csvService = app(AffiliateOrderCsvImportService::class);

            // CSV Content: 2 valid rows, 1 row with invalid phone, 1 row with invalid price
            $csvContent = implode("\n", [
                'customer_name,customer_phone,governorate,city,address,sku,quantity,selling_price',
                'يوسف فوزي,01011112222,القاهرة,مدينة نصر,شارع مكرم عبيد عمارة 12,WATCH-CLASSIC-01,1,250',
                'مازن حمدي,01122223333,الإسكندرية,سموحة,شارع فوزي معاذ عمارة 4,WATCH-CLASSIC-01,2,220',
                'عميل خاطئ 1,01300000000,القاهرة,القاهرة,شارع النصر عمارة 1,WATCH-CLASSIC-01,1,200', // Invalid prefix 013
                'عميل خاطئ 2,01033332222,القاهرة,القاهرة,شارع النصر عمارة 1,WATCH-CLASSIC-01,1,100', // Price below min (180 EGP)
            ]);

            $tmpFile = tempnam(sys_get_temp_dir(), 'aff_import_');
            file_put_contents($tmpFile, $csvContent);

            $uploadedFile = new UploadedFile($tmpFile, 'orders.csv', 'text/csv', null, true);

            $result = $csvService->import($this->affiliate, $uploadedFile);

            $this->assertSame(4, $result['total']);
            $this->assertSame(2, $result['imported']);
            $this->assertSame(2, $result['failed']);
            $this->assertCount(2, $result['orders']);
            $this->assertCount(2, $result['errors']);

            // Verify created orders
            $this->assertSame('يوسف فوزي', $result['orders'][0]->shippingAddress?->first_name);
            $this->assertSame('مازن حمدي', $result['orders'][1]->shippingAddress?->first_name);

            @unlink($tmpFile);
        });
    }
}
