<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Affiliate\Settings\AffiliateProgramSettings;
use App\Domain\Finance\DTOs\EntryData;
use App\Domain\Finance\DTOs\LedgerLineData;
use App\Domain\Finance\Services\AccountResolver;
use App\Domain\Finance\Services\Ledger;
use App\Domain\Orders\Enums\OrderStatus;
use App\Jobs\ProvisionTenant;
use App\Models\Affiliate;
use App\Models\AffiliateLevel;
use App\Models\InventoryBalance;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Supplier;
use App\Models\SupplierBatch;
use App\Models\Tenant;
use App\Models\Warehouse;
use App\Models\WholesalePrice;
use App\Support\Location\Governorate;
use App\Support\Settings\GeneralSettings;
use App\Support\Settings\ShippingSettings;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Lunar\Base\ValueObjects\Cart\TaxBreakdown;
use Lunar\FieldTypes\Text;
use Lunar\Models\Channel;
use Lunar\Models\Currency;
use Lunar\Models\Order;
use Lunar\Models\OrderAddress;
use Lunar\Models\OrderLine;
use Lunar\Models\Product;
use Lunar\Models\ProductType;
use Lunar\Models\ProductVariant;
use Lunar\Models\TaxClass;

class PilotStagingSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Ensure Prerequisites
        $this->call(EgyptGovernoratesSeeder::class);
        $this->call(PlanSeeder::class);

        /** @var Plan|null $proPlan */
        $proPlan = Plan::where('slug', 'pro')->first() ?? Plan::first();

        // 2. Merchant Profiles Definition
        $merchants = [
            [
                'id' => 'cairo-electronics',
                'name' => 'إلكترونيات القاهرة للدروبشيبينج',
                'domain' => 'cairo-electronics.localhost',
                'owner' => [
                    'name' => 'طارق كمال',
                    'email' => 'tarek@cairo-electronics.com',
                    'password' => 'secret123',
                ],
                'supplier' => [
                    'name' => 'الحاج رضوان للتوريدات',
                    'company' => 'مؤسسة رضوان للتجارة والاستيراد',
                    'phone' => '01011122233',
                ],
                'products' => [
                    [
                        'name' => 'سماعات بلوتوث لاسلكية برو TWS',
                        'sku' => 'EARBUDS-TWS-01',
                        'wholesale' => 12000, // 120 EGP
                        'min' => 18000,       // 180 EGP
                        'max' => 28000,       // 280 EGP
                        'stock' => 50,
                    ],
                    [
                        'name' => 'ساعة ذكية مقاومة للماء مع شاشة AMOLED',
                        'sku' => 'SMARTWATCH-V2',
                        'wholesale' => 35000, // 350 EGP
                        'min' => 48000,       // 480 EGP
                        'max' => 65000,       // 650 EGP
                        'stock' => 30,
                    ],
                    [
                        'name' => 'باور بنك شحن فائق السرعة 20,000 مللي أمبير',
                        'sku' => 'POWERBANK-20K',
                        'wholesale' => 22000, // 220 EGP
                        'min' => 32000,       // 320 EGP
                        'max' => 45000,       // 450 EGP
                        'stock' => 4, // low stock warning
                    ],
                ],
            ],
            [
                'id' => 'delta-fashion',
                'name' => 'أزياء الدلتا للأفلييت والملابس',
                'domain' => 'delta-fashion.localhost',
                'owner' => [
                    'name' => 'محمود البدوي',
                    'email' => 'badawi@delta-fashion.com',
                    'password' => 'secret123',
                ],
                'supplier' => [
                    'name' => 'مصنع المحلة للأنسجة',
                    'company' => 'شركة المحلة الكبرى للمنسوجات',
                    'phone' => '01122233344',
                ],
                'products' => [
                    [
                        'name' => 'سويت شيرت ميلتون شتوي مبطن أوفر سايز',
                        'sku' => 'HOODIE-OVR-01',
                        'wholesale' => 19000, // 190 EGP
                        'min' => 28000,       // 280 EGP
                        'max' => 39000,       // 390 EGP
                        'stock' => 75,
                    ],
                    [
                        'name' => 'بنطلون جوجر رياضي قطن 100%',
                        'sku' => 'JOGGER-PANTS-BLK',
                        'wholesale' => 14000, // 140 EGP
                        'min' => 22000,       // 220 EGP
                        'max' => 32000,       // 320 EGP
                        'stock' => 40,
                    ],
                ],
            ],
            [
                'id' => 'alex-cosmetics',
                'name' => 'مستحضرات الإسكندرية والعناية',
                'domain' => 'alex-cosmetics.localhost',
                'owner' => [
                    'name' => 'نيرة الشريف',
                    'email' => 'nayera@alex-cosmetics.com',
                    'password' => 'secret123',
                ],
                'supplier' => [
                    'name' => 'معامل فارما بيوتي',
                    'company' => 'فارما بيوتي لمستحضرات التجميل',
                    'phone' => '01233344455',
                ],
                'products' => [
                    [
                        'name' => 'سيروم حمض الهيالورونيك وفيتامين سي 50 مل',
                        'sku' => 'SERUM-VITC-50',
                        'wholesale' => 9500,  // 95 EGP
                        'min' => 16000,       // 160 EGP
                        'max' => 25000,       // 250 EGP
                        'stock' => 60,
                    ],
                    [
                        'name' => 'كريم الكولاجين للترطيب العميق ومحاربة التجاعيد',
                        'sku' => 'CREAM-COLLAGEN',
                        'wholesale' => 11000, // 110 EGP
                        'min' => 19000,       // 190 EGP
                        'max' => 29000,       // 290 EGP
                        'stock' => 0, // stockout
                    ],
                ],
            ],
        ];

        // 3. Seed Each Pilot Merchant
        foreach ($merchants as $data) {
            $job = new ProvisionTenant(
                tenantId: $data['id'],
                name: $data['name'],
                domain: $data['domain'],
                ownerData: $data['owner']
            );
            $tenant = $job->handle();

            // Upgrade to Pro plan with all operational features
            if ($proPlan instanceof Plan) {
                Subscription::updateOrCreate(
                    ['tenant_id' => $tenant->id],
                    [
                        'plan_id' => $proPlan->id,
                        'status' => 'active',
                        'trial_ends_at' => null,
                        'current_period_ends_at' => now()->addYear(),
                    ]
                );
            }

            // Populate Tenant Isolated Store Data
            $tenant->run(function () use ($tenant, $data) {
                $this->seedTenantStoreData($tenant, $data);
            });
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function seedTenantStoreData(Tenant $tenant, array $data): void
    {
        // 1. Settings
        $general = GeneralSettings::load();
        $general->store_name = $data['name'];
        $general->save();

        $shipping = ShippingSettings::load();
        $shipping->allow_cod = true;
        $shipping->cod_fee_minor = 1500; // 15 EGP
        $shipping->save();

        $affiliateSettings = AffiliateProgramSettings::load();
        $affiliateSettings->min_commission_minor = 1000;  // 10 EGP
        $affiliateSettings->max_commission_minor = 100000; // 1,000 EGP
        $affiliateSettings->min_selling_markup_minor = 2000; // 20 EGP
        $affiliateSettings->max_selling_markup_minor = 200000; // 2,000 EGP
        $affiliateSettings->save();

        // 2. Default Warehouse
        $warehouse = Warehouse::firstOrCreate(
            ['tenant_id' => $tenant->id, 'code' => 'MAIN-WH'],
            [
                'name' => 'المخزن الرئيسي',
                'is_active' => true,
                'is_default' => true,
            ]
        );

        // 3. Affiliate Levels
        $silverLevel = AffiliateLevel::firstOrCreate(
            ['tenant_id' => $tenant->id, 'slug' => 'silver'],
            [
                'name_ar' => 'فضي',
                'name_en' => 'Silver',
                'tier_order' => 1,
                'min_delivered_orders' => 0,
                'bonus_per_order_minor' => 0,
            ]
        );
        $goldLevel = AffiliateLevel::firstOrCreate(
            ['tenant_id' => $tenant->id, 'slug' => 'gold'],
            [
                'name_ar' => 'ذهبي',
                'name_en' => 'Gold',
                'tier_order' => 2,
                'min_delivered_orders' => 20,
                'bonus_per_order_minor' => 1500,
            ]
        );
        $vipLevel = AffiliateLevel::firstOrCreate(
            ['tenant_id' => $tenant->id, 'slug' => 'vip'],
            [
                'name_ar' => 'VIP بلاتيني',
                'name_en' => 'VIP',
                'tier_order' => 3,
                'min_delivered_orders' => 50,
                'bonus_per_order_minor' => 3000,
            ]
        );

        // 4. Supplier
        $supplierData = $data['supplier'];
        /** @var Supplier $supplier */
        $supplier = Supplier::firstOrCreate(
            ['tenant_id' => $tenant->id, 'phone' => $supplierData['phone']],
            [
                'name' => $supplierData['name'],
                'company_name' => $supplierData['company'],
                'status' => Supplier::STATUS_ACTIVE,
                'default_lead_time_days' => 2,
            ]
        );

        // 5. Products & Wholesale Pricing
        $productType = ProductType::where('tenant_id', $tenant->id)->first() ?? ProductType::create([
            'tenant_id' => $tenant->id,
            'name' => 'عام',
        ]);
        $taxClass = TaxClass::where('tenant_id', $tenant->id)->first() ?? TaxClass::create([
            'tenant_id' => $tenant->id,
            'name' => 'افتراضي',
            'default' => true,
        ]);
        $currency = Currency::where('tenant_id', $tenant->id)->first() ?? Currency::create([
            'tenant_id' => $tenant->id,
            'code' => 'EGP',
            'name' => 'Egyptian Pound',
            'exchange_rate' => 1,
            'decimal_places' => 2,
            'default' => true,
            'enabled' => true,
        ]);

        $createdVariants = [];
        foreach ($data['products'] as $prodData) {
            $product = Product::create([
                'tenant_id' => $tenant->id,
                'product_type_id' => $productType->id,
                'status' => 'published',
                'attribute_data' => [
                    'name' => new Text($prodData['name']),
                    'description' => new Text("وصف تفصيلي عالي الجودة لـ {$prodData['name']}"),
                ],
            ]);

            $variant = ProductVariant::create([
                'tenant_id' => $tenant->id,
                'product_id' => $product->id,
                'tax_class_id' => $taxClass->id,
                'sku' => $prodData['sku'],
                'unit_quantity' => 1,
                'stock' => $prodData['stock'],
            ]);

            // Reservation reads warehouse balances, not variants.stock: keep them in step.
            InventoryBalance::create([
                'tenant_id' => $tenant->id,
                'warehouse_id' => $warehouse->id,
                'product_variant_id' => $variant->id,
                'on_hand' => $prodData['stock'],
                'reserved' => 0,
            ]);

            // Add standard Lunar retail price
            $variant->prices()->create([
                'tenant_id' => $tenant->id,
                'currency_id' => $currency->id,
                'price' => $prodData['min'],
            ]);

            // Wholesale & Supplier Association
            WholesalePrice::create([
                'tenant_id' => $tenant->id,
                'product_variant_id' => $variant->id,
                'supplier_id' => $supplier->id,
                'wholesale_price_minor' => $prodData['wholesale'],
                'min_price_minor' => $prodData['min'],
                'max_price_minor' => $prodData['max'],
                'min_commission_minor' => 1500,  // 15 EGP per-product override
                'max_commission_minor' => 15000, // 150 EGP per-product override
                'status' => WholesalePrice::STATUS_ACTIVE,
            ]);

            $createdVariants[] = $variant;
        }

        // 6. Record Initial Supply Batch from Supplier
        if (count($createdVariants) > 0) {
            $firstVariant = $createdVariants[0];
            $batchCostMinor = 12000;
            $batchQty = 50;
            $batchTotal = $batchCostMinor * $batchQty;

            $batch = SupplierBatch::create([
                'tenant_id' => $tenant->id,
                'batch_number' => 'SUP-BATCH-INIT-'.strtoupper(Str::random(6)),
                'supplier_id' => $supplier->id,
                'product_variant_id' => $firstVariant->id,
                'quantity_supplied' => $batchQty,
                'unit_cost_minor' => $batchCostMinor,
                'total_cost_minor' => $batchTotal,
                'status' => SupplierBatch::STATUS_RECEIVED,
                'received_at' => now()->subDays(5),
            ]);

            // Double entry: Dr store:inventory / Cr supplier:{id}:payable
            $accountResolver = app(AccountResolver::class);
            $ledger = app(Ledger::class);

            $invAccount = $accountResolver->getStoreInventoryAccount();
            $supAccount = $accountResolver->getSupplierPayableAccount($supplier);

            $ledger->post(new EntryData(
                type: 'supplier_initial_supply',
                idempotencyKey: "supplier_initial_batch_{$batch->id}",
                description: "توريد بضاعة ابتدائية من المورد {$supplier->name}",
                lines: [
                    new LedgerLineData(account: $invAccount, amountMinor: $batchTotal),
                    new LedgerLineData(account: $supAccount, amountMinor: -$batchTotal),
                ],
                refType: SupplierBatch::class,
                refId: (int) $batch->id,
                createdBy: null
            ));
        }

        // 7. Affiliates
        $affiliates = [
            [
                'name' => 'أحمد هاني',
                'email' => 'ahmed@affiliate.local',
                'phone' => '+201011112222',
                'code' => 'AFF-CAIRO-01',
                'affiliate_level_id' => $vipLevel->id,
                'quality_score' => 92,
            ],
            [
                'name' => 'ياسمين ممدوح',
                'email' => 'yasmin@affiliate.local',
                'phone' => '+201033334444',
                'code' => 'AFF-CAIRO-02',
                'affiliate_level_id' => $goldLevel->id,
                'quality_score' => 84,
            ],
            [
                'name' => 'عمر سليم',
                'email' => 'omar@affiliate.local',
                'phone' => '+201055556666',
                'code' => 'AFF-CAIRO-03',
                'affiliate_level_id' => $silverLevel->id,
                'quality_score' => 71,
            ],
        ];

        $createdAffiliates = [];
        foreach ($affiliates as $affData) {
            $aff = Affiliate::create([
                'tenant_id' => $tenant->id,
                'name' => $affData['name'],
                'email' => $affData['email'],
                'phone' => $affData['phone'],
                'code' => $affData['code'],
                'password' => Hash::make('password123'),
                'status' => Affiliate::STATUS_ACTIVE,
                'kyc_status' => 'approved',
                'affiliate_level_id' => $affData['affiliate_level_id'],
                'quality_score' => $affData['quality_score'],
                'phone_verified_at' => now(),
            ]);
            $createdAffiliates[] = $aff;
        }

        // 8. Governorates
        $cairo = Governorate::where('code', 'CAI')->first() ?? Governorate::first();
        $giza = Governorate::where('code', 'GZ')->first() ?? Governorate::skip(1)->first() ?? $cairo;

        // 9. Orders Lifecycle Cohort
        if (count($createdVariants) > 0 && count($createdAffiliates) > 0 && $cairo) {
            $v1 = $createdVariants[0];
            $aff1 = $createdAffiliates[0];

            $orderConfigs = [
                ['status' => OrderStatus::PENDING_CONFIRMATION->value, 'selling' => 20000, 'profit' => 8000],
                ['status' => OrderStatus::CONFIRMED->value, 'selling' => 21000, 'profit' => 9000],
                ['status' => OrderStatus::SHIPPED->value, 'selling' => 22000, 'profit' => 10000],
                ['status' => OrderStatus::DELIVERED->value, 'selling' => 22000, 'profit' => 10000],
                ['status' => OrderStatus::SETTLED->value, 'selling' => 23000, 'profit' => 11000],
                ['status' => OrderStatus::CANCELLED_NO_ANSWER->value, 'selling' => 20000, 'profit' => 8000],
                ['status' => OrderStatus::RETURNED->value, 'selling' => 21000, 'profit' => 9000],
            ];

            $channel = Channel::getDefault() ?? Channel::first();

            foreach ($orderConfigs as $idx => $cfg) {
                $ref = 'ORD-PLT-'.strtoupper(Str::random(6));
                $sellingMinor = $cfg['selling'];
                $profitMinor = $cfg['profit'];
                $shippingMinor = 4500;
                $codMinor = 1500;
                $totalMinor = $sellingMinor + $shippingMinor + $codMinor;

                $order = Order::create([
                    'tenant_id' => $tenant->id,
                    'reference' => $ref,
                    'status' => $cfg['status'],
                    'customer_reference' => 'CUST-'.($idx + 1),
                    'channel_id' => $channel?->id,
                    'currency_code' => 'EGP',
                    'affiliate_id' => $aff1->id,
                    'sub_total' => $sellingMinor,
                    'shipping_total' => $shippingMinor,
                    'tax_total' => 0,
                    'discount_total' => 0,
                    'tax_breakdown' => new TaxBreakdown,
                    'total' => $totalMinor,
                    'customer_phone_e164' => '+2010998877'.str_pad((string) $idx, 2, '0', STR_PAD_LEFT),
                    'customer_phone_hash' => hash('sha256', '+2010998877'.str_pad((string) $idx, 2, '0', STR_PAD_LEFT)),
                    'governorate_id' => $idx % 2 === 0 ? $cairo->id : ($giza ? $giza->id : $cairo->id),
                    'price_snapshot' => json_encode([
                        'wholesale_price_minor' => 12000,
                        'selling_price_minor' => $sellingMinor,
                        'affiliate_profit_minor' => $profitMinor,
                        'shipping_cost_minor' => $shippingMinor,
                        'cod_fee_minor' => $codMinor,
                        'customer_total_minor' => $totalMinor,
                    ]),
                ]);

                // Order Line
                OrderLine::create([
                    'tenant_id' => $tenant->id,
                    'order_id' => $order->id,
                    'purchasable_type' => ProductVariant::class,
                    'purchasable_id' => $v1->id,
                    'type' => 'physical',
                    'identifier' => $v1->sku,
                    'description' => 'سماعات بلوتوث',
                    'unit_price' => $sellingMinor,
                    'sub_total' => $sellingMinor,
                    'tax_total' => 0,
                    'discount_total' => 0,
                    'tax_breakdown' => new TaxBreakdown,
                    'total' => $sellingMinor,
                    'quantity' => 1,
                    'unit_quantity' => 1,
                ]);

                // Address
                OrderAddress::create([
                    'tenant_id' => $tenant->id,
                    'order_id' => $order->id,
                    'type' => 'shipping',
                    'first_name' => 'عميل تجريبي '.($idx + 1),
                    'contact_phone' => '+2010998877'.str_pad((string) $idx, 2, '0', STR_PAD_LEFT),
                    'line_one' => 'شارع التحرير، عمارة 12',
                    'city' => 'وسط البلد',
                ]);

                // Double entry financial posting for settled order
                if ($cfg['status'] === OrderStatus::SETTLED->value) {
                    $accountResolver = app(AccountResolver::class);
                    $ledger = app(Ledger::class);

                    $bank = $accountResolver->getStoreBankAccount();
                    $shippingExp = $accountResolver->getStoreShippingExpenseAccount();
                    $codRec = $accountResolver->getPlatformCodReceivableAccount();
                    $commExp = $accountResolver->getStoreCommissionExpenseAccount();
                    $affAvail = $accountResolver->getAffiliateAvailableAccount($aff1);

                    // 1. Remittance Settlement entry (COD cleared)
                    $ledger->post(new EntryData(
                        type: 'cod_remittance_settled',
                        idempotencyKey: "cod_settle_plt_{$order->id}",
                        description: "تسوية تحصيل الطلب #{$order->reference}",
                        lines: [
                            new LedgerLineData(account: $bank, amountMinor: $totalMinor - $shippingMinor),
                            new LedgerLineData(account: $shippingExp, amountMinor: $shippingMinor),
                            new LedgerLineData(account: $codRec, amountMinor: -$totalMinor),
                        ],
                        refType: Order::class,
                        refId: (int) $order->id,
                        createdBy: null
                    ));

                    // 2. Affiliate Commission released entry
                    $ledger->post(new EntryData(
                        type: 'commission_released',
                        idempotencyKey: "comm_release_plt_{$order->id}",
                        description: "إفراج عمولة المسوق للطلب #{$order->reference}",
                        lines: [
                            new LedgerLineData(account: $commExp, amountMinor: $profitMinor),
                            new LedgerLineData(account: $affAvail, amountMinor: -$profitMinor),
                        ],
                        refType: Order::class,
                        refId: (int) $order->id,
                        createdBy: null
                    ));
                }
            }
        }
    }
}
