<?php

declare(strict_types=1);

namespace App\Domain\Onboarding\Services;

use App\Domain\Orders\Enums\OrderStatus;
use App\Models\Affiliate;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\Tenant;
use App\Models\TrackingLink;
use App\Models\WholesalePrice;
use App\Support\Location\Governorate;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Lunar\Base\ValueObjects\Cart\TaxBreakdown;
use Lunar\FieldTypes\Text;
use Lunar\Models\Channel;
use Lunar\Models\Country;
use Lunar\Models\Currency;
use Lunar\Models\Language;
use Lunar\Models\Order;
use Lunar\Models\OrderAddress;
use Lunar\Models\OrderLine;
use Lunar\Models\Price;
use Lunar\Models\Product;
use Lunar\Models\ProductType;
use Lunar\Models\ProductVariant;
use Lunar\Models\TaxClass;

class TenantDemoDataService
{
    /**
     * Check if tenant currently has demo data seeded.
     */
    public function hasDemoData(Tenant $tenant): bool
    {
        return $tenant->run(function () use ($tenant) {
            return ProductVariant::where('tenant_id', $tenant->id)
                ->where('sku', 'LIKE', 'DEMO-%')
                ->exists();
        });
    }

    /**
     * Seed 5 sample products, 2 affiliates with tracking links, and 3 sample orders.
     *
     * @return array<string, int>
     */
    public function seedDemoData(Tenant $tenant): array
    {
        return $tenant->run(function () use ($tenant) {
            $tenantId = (string) $tenant->id;

            return DB::transaction(function () use ($tenantId) {
                Language::firstOrCreate(['code' => 'ar'], ['name' => 'Arabic', 'default' => true]);

                $country = Country::firstOrCreate(
                    ['iso3' => 'EGY'],
                    [
                        'name' => 'Egypt',
                        'iso2' => 'EG',
                        'phonecode' => '+20',
                        'currency' => Money::defaultCurrency(),
                        'emoji' => '🇪🇬',
                        'emoji_u' => 'U+1F1EA U+1F1EC',
                    ]
                );

                $channel = Channel::where('tenant_id', $tenantId)->first() ?? Channel::create([
                    'tenant_id' => $tenantId,
                    'name' => 'Default',
                    'handle' => 'default',
                    'default' => true,
                ]);

                $productType = ProductType::where('tenant_id', $tenantId)->first() ?? ProductType::create([
                    'tenant_id' => $tenantId,
                    'name' => 'Standard',
                ]);

                $taxClass = TaxClass::where('tenant_id', $tenantId)->first() ?? TaxClass::create([
                    'tenant_id' => $tenantId,
                    'name' => 'Standard',
                ]);

                $currency = Currency::where('tenant_id', $tenantId)->where('default', true)->first()
                    ?? Currency::create([
                        'tenant_id' => $tenantId,
                        'code' => 'EGP',
                        'name' => 'Egyptian Pound',
                        'exchange_rate' => 1.0,
                        'decimal_places' => 2,
                        'enabled' => true,
                        'default' => true,
                    ]);

                // 1. Seed 5 Sample Products
                $demoProductsData = [
                    ['name' => 'سماعات بلوتوث عازلة للضوضاء برو', 'sku' => 'DEMO-EARPHONES-01', 'price' => 59900, 'wholesale' => 38000, 'commission' => 12000],
                    ['name' => 'ساعة ذكية رياضية ببطارية 14 يوماً', 'sku' => 'DEMO-SMARTWATCH-02', 'price' => 89900, 'wholesale' => 58000, 'commission' => 18000],
                    ['name' => 'شاحن مغناطيسي سريع 3 في 1 للمكتب', 'sku' => 'DEMO-CHARGER-03', 'price' => 34900, 'wholesale' => 21000, 'commission' => 7000],
                    ['name' => 'حقيبة ظهر للأعمال واللابتوب مقاومة للماء', 'sku' => 'DEMO-BACKPACK-04', 'price' => 49900, 'wholesale' => 31000, 'commission' => 10000],
                    ['name' => 'مصباح مكتبي ذكي بحساس إضاءة وحامل هاتف', 'sku' => 'DEMO-SMARTLAMP-05', 'price' => 27900, 'wholesale' => 16000, 'commission' => 6000],
                ];

                $createdVariants = [];
                foreach ($demoProductsData as $pData) {
                    /** @var Product $product */
                    $product = Product::create([
                        'tenant_id' => $tenantId,
                        'product_type_id' => $productType->id,
                        'status' => 'published',
                        'attribute_data' => [
                            'name' => new Text($pData['name']),
                        ],
                    ]);

                    /** @var ProductVariant $variant */
                    $variant = ProductVariant::create([
                        'tenant_id' => $tenantId,
                        'product_id' => $product->id,
                        'sku' => $pData['sku'],
                        'tax_class_id' => $taxClass->id,
                        'stock' => 100,
                    ]);

                    Price::create([
                        'tenant_id' => $tenantId,
                        'priceable_type' => $variant->getMorphClass(),
                        'priceable_id' => $variant->id,
                        'currency_id' => $currency->id,
                        'price' => $pData['price'],
                    ]);

                    WholesalePrice::create([
                        'tenant_id' => $tenantId,
                        'product_variant_id' => $variant->id,
                        'wholesale_price_minor' => $pData['wholesale'],
                        'recommended_retail_price_minor' => $pData['price'],
                        'minimum_affiliate_commission_minor' => $pData['commission'],
                        'is_active' => true,
                    ]);

                    $createdVariants[] = $variant;
                }

                // 2. Seed 2 Sample Affiliates & Tracking Links
                $affiliatesData = [
                    ['name' => 'محمد سمير (مسوق تجريبي)', 'code' => 'DEMO-AFF-01', 'phone' => '+201099112233'],
                    ['name' => 'نورهان عادل (مسوقة تجريبية)', 'code' => 'DEMO-AFF-02', 'phone' => '+201188223344'],
                ];

                $createdAffiliates = [];
                foreach ($affiliatesData as $affData) {
                    /** @var Affiliate $affiliate */
                    $affiliate = Affiliate::create([
                        'tenant_id' => $tenantId,
                        'name' => $affData['name'],
                        'code' => $affData['code'],
                        'phone' => $affData['phone'],
                        'email' => strtolower($affData['code'])."@{$tenantId}.test",
                        'password' => bcrypt('demo123456'),
                        'status' => Affiliate::STATUS_ACTIVE,
                        'phone_verified_at' => now(),
                    ]);

                    TrackingLink::create([
                        'tenant_id' => $tenantId,
                        'affiliate_id' => $affiliate->id,
                        'code' => "DEMO-TRK-{$affiliate->id}",
                        'slug' => "demo-ref-{$affiliate->id}",
                        'destination_url' => "https://{$tenantId}.affiliate-os.com",
                        'is_active' => true,
                    ]);

                    $createdAffiliates[] = $affiliate;
                }

                // 3. Seed Demo Customer & Address
                /** @var Customer $customer */
                $customer = Customer::create([
                    'tenant_id' => $tenantId,
                    'first_name' => 'عميل',
                    'last_name' => 'تجريبي',
                    'email' => "demo-cust-{$tenantId}@example.test",
                    'phone' => '01012345678',
                    'is_active' => true,
                ]);

                $cairo = Governorate::where('code', 'CAI')->first();
                $govId = $cairo ? $cairo->id : 1;

                CustomerAddress::create([
                    'tenant_id' => $tenantId,
                    'customer_id' => $customer->id,
                    'name' => 'عميل تجريبي',
                    'phone' => '01012345678',
                    'governorate_id' => $govId,
                    'city' => 'المعادي',
                    'address_line' => 'شارع 9 عمارة 12 الدور 3',
                    'is_default' => true,
                ]);

                // 4. Seed 3 Sample Orders in different stages
                $ordersStatuses = [
                    ['ref' => 'DEMO-ORD-101', 'status' => OrderStatus::PENDING_CONFIRMATION->value, 'variant' => $createdVariants[0], 'aff' => $createdAffiliates[0]],
                    ['ref' => 'DEMO-ORD-102', 'status' => OrderStatus::CONFIRMED->value, 'variant' => $createdVariants[1], 'aff' => $createdAffiliates[1]],
                    ['ref' => 'DEMO-ORD-103', 'status' => OrderStatus::DELIVERED->value, 'variant' => $createdVariants[2], 'aff' => $createdAffiliates[0]],
                ];

                foreach ($ordersStatuses as $oData) {
                    /** @var ProductVariant $oVariant */
                    $oVariant = $oData['variant'];
                    /** @var Price|null $variantPrice */
                    $variantPrice = Price::where('priceable_id', $oVariant->id)->first();
                    $unitPrice = $variantPrice ? (int) $variantPrice->price->value : 50000;
                    $shipping = 5000;
                    $total = $unitPrice + $shipping;

                    /** @var Order $order */
                    $order = Order::create([
                        'tenant_id' => $tenantId,
                        'storefront_customer_id' => $customer->id,
                        'channel_id' => $channel->id,
                        'status' => $oData['status'],
                        'reference' => $oData['ref'],
                        'currency_code' => Money::defaultCurrency(),
                        'sub_total' => $unitPrice,
                        'discount_total' => 0,
                        'shipping_total' => $shipping,
                        'tax_breakdown' => new TaxBreakdown,
                        'tax_total' => 0,
                        'total' => $total,
                    ]);

                    OrderLine::create([
                        'tenant_id' => $tenantId,
                        'order_id' => $order->id,
                        'purchasable_type' => $oVariant->getMorphClass(),
                        'purchasable_id' => $oVariant->id,
                        'type' => 'physical',
                        'description' => 'منتج تجريبي',
                        'identifier' => $oVariant->sku,
                        'quantity' => 1,
                        'unit_price' => $unitPrice,
                        'sub_total' => $unitPrice,
                        'discount_total' => 0,
                        'tax_breakdown' => new TaxBreakdown,
                        'tax_total' => 0,
                        'total' => $unitPrice,
                    ]);

                    OrderAddress::create([
                        'tenant_id' => $tenantId,
                        'order_id' => $order->id,
                        'first_name' => 'عميل',
                        'last_name' => 'تجريبي',
                        'contact_phone' => '01012345678',
                        'contact_email' => "demo-cust-{$tenantId}@example.test",
                        'line_one' => 'شارع 9 عمارة 12',
                        'city' => 'المعادي',
                        'country_id' => $country->id,
                        'type' => 'shipping',
                    ]);
                }

                return [
                    'products' => 5,
                    'affiliates' => 2,
                    'orders' => 3,
                    'customers' => 1,
                ];
            });
        });
    }

    /**
     * Purge all demo data without touching any real merchant products or orders.
     *
     * @return array<string, int>
     */
    public function purgeDemoData(Tenant $tenant): array
    {
        return $tenant->run(function () use ($tenant) {
            $tenantId = (string) $tenant->id;

            return DB::transaction(function () use ($tenantId) {
                // 1. Purge Demo Orders
                $demoOrderIds = Order::where('tenant_id', $tenantId)
                    ->where('reference', 'LIKE', 'DEMO-%')
                    ->pluck('id');

                $ordersCount = $demoOrderIds->count();
                if ($ordersCount > 0) {
                    OrderLine::where('tenant_id', $tenantId)->whereIn('order_id', $demoOrderIds)->delete();
                    OrderAddress::where('tenant_id', $tenantId)->whereIn('order_id', $demoOrderIds)->delete();
                    Order::where('tenant_id', $tenantId)->whereIn('id', $demoOrderIds)->delete();
                }

                // 2. Purge Demo Affiliates & Tracking Links
                $demoAffiliateIds = Affiliate::where('tenant_id', $tenantId)
                    ->where('code', 'LIKE', 'DEMO-%')
                    ->pluck('id');

                $affiliatesCount = $demoAffiliateIds->count();
                if ($affiliatesCount > 0) {
                    TrackingLink::where('tenant_id', $tenantId)->whereIn('affiliate_id', $demoAffiliateIds)->delete();
                    Affiliate::where('tenant_id', $tenantId)->whereIn('id', $demoAffiliateIds)->delete();
                }

                // 3. Purge Demo Products & Variants
                $demoVariants = ProductVariant::where('tenant_id', $tenantId)
                    ->where('sku', 'LIKE', 'DEMO-%')
                    ->get();

                $productsCount = 0;
                if ($demoVariants->isNotEmpty()) {
                    $variantIds = $demoVariants->pluck('id');
                    $productIds = $demoVariants->pluck('product_id')->unique();
                    $productsCount = $productIds->count();

                    WholesalePrice::where('tenant_id', $tenantId)->whereIn('product_variant_id', $variantIds)->delete();
                    Price::where('tenant_id', $tenantId)->where('priceable_type', (new ProductVariant)->getMorphClass())->whereIn('priceable_id', $variantIds)->delete();
                    ProductVariant::where('tenant_id', $tenantId)->whereIn('id', $variantIds)->delete();
                    Product::where('tenant_id', $tenantId)->whereIn('id', $productIds)->delete();
                }

                // 4. Purge Demo Customers
                $customersCount = Customer::where('tenant_id', $tenantId)
                    ->where('email', 'LIKE', "demo-cust-{$tenantId}%")
                    ->delete();

                return [
                    'purged_products' => $productsCount,
                    'purged_affiliates' => $affiliatesCount,
                    'purged_orders' => $ordersCount,
                    'purged_customers' => $customersCount,
                ];
            });
        });
    }
}
