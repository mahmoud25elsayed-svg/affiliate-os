<?php

namespace Tests\Feature;

use App\Models\Affiliate;
use App\Models\Tenant;
use App\Models\WholesalePrice;
use Lunar\Base\ValueObjects\Cart\TaxBreakdown;
use Lunar\FieldTypes\Text;
use Lunar\Models\Cart;
use Lunar\Models\Channel;
use Lunar\Models\Currency;
use Lunar\Models\Order;
use Lunar\Models\Product;
use Lunar\Models\ProductType;
use Lunar\Models\ProductVariant;
use Lunar\Models\TaxClass;
use Tests\TestCase;

class LunarExtensionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->ensureTenantExists('tenant1');
        $this->ensureTenantExists('tenant2');

        // Register dynamic relation on Lunar ProductVariant without touching vendor
        ProductVariant::resolveRelationUsing('wholesalePrice', function ($variant) {
            return $variant->hasOne(WholesalePrice::class, 'product_variant_id');
        });
    }

    public function test_can_extend_product_variant_with_custom_wholesale_price_table(): void
    {
        $tenant = Tenant::find('tenant1');

        $tenant->run(function () {
            $variant = ProductVariant::first();
            if (! $variant) {
                $productType = ProductType::first() ?? ProductType::create(['name' => 'Default']);
                $taxClass = TaxClass::first() ?? TaxClass::create(['name' => 'Default']);
                $product = Product::create([
                    'product_type_id' => $productType->id,
                    'status' => 'published',
                    'attribute_data' => [
                        'name' => new Text('Test Product'),
                    ],
                ]);
                $variant = ProductVariant::create([
                    'product_id' => $product->id,
                    'sku' => 'SKU-TEST-WHOLESALE',
                    'tax_class_id' => $taxClass->id,
                ]);
            }
            $this->assertNotNull($variant);

            // Create custom wholesale price
            WholesalePrice::updateOrCreate(
                ['product_variant_id' => $variant->id],
                ['wholesale_price_minor' => 15000] // 150.00 EGP
            );

            // Access via dynamic relation on Lunar model
            $variant->load('wholesalePrice');
            $this->assertNotNull($variant->wholesalePrice);
            $this->assertEquals(15000, $variant->wholesalePrice->wholesale_price_minor);
        });
    }

    public function test_custom_order_status_is_configured_and_usable(): void
    {
        $tenant = Tenant::find('tenant1');

        $tenant->run(function () {
            $statuses = config('lunar.orders.statuses');
            $this->assertArrayHasKey('cod-confirmed', $statuses);
            $this->assertEquals('COD Confirmed', $statuses['cod-confirmed']['label']);

            $channel = Channel::first();

            // Create an order with custom status
            $order = Order::create([
                'channel_id' => $channel->id,
                'status' => 'cod-confirmed',
                'sub_total' => 10000,
                'total' => 10000,
                'discount_total' => 0,
                'shipping_total' => 0,
                'tax_total' => 0,
                'tax_breakdown' => new TaxBreakdown,
                'currency_code' => 'EGP',
            ]);

            $this->assertEquals('cod-confirmed', $order->status);
            $this->assertTrue(Order::where('status', 'cod-confirmed')->exists());
        });
    }

    public function test_custom_cart_pipeline_executes_during_calculation(): void
    {
        $tenant = Tenant::find('tenant1');

        $tenant->run(function () {
            $currency = Currency::first();
            $channel = Channel::first();

            $cart = Cart::create([
                'channel_id' => $channel->id,
                'currency_id' => $currency->id,
            ]);

            $this->assertArrayNotHasKey('cod_fee_applied', (array) ($cart->meta ?? []));

            // Run Lunar calculation pipeline
            $cart->calculate();

            $meta = (array) $cart->meta;
            $this->assertArrayHasKey('cod_fee_applied', $meta);
            $this->assertTrue($meta['cod_fee_applied']);
            $this->assertEquals(2500, $meta['cod_fee_amount_minor']);
        });
    }

    public function test_tenant_affiliate_module_is_isolated_between_tenants(): void
    {
        $tenant1 = Tenant::find('tenant1');
        $tenant2 = Tenant::find('tenant2');

        // Create in Tenant 1
        $tenant1->run(function () {
            Affiliate::query()->delete();
            Affiliate::create([
                'name' => 'Marketer Ahmed',
                'code' => 'AHMED10',
                'commission_rate' => 15,
            ]);

            $this->assertEquals(1, Affiliate::count());
            $this->assertEquals('AHMED10', Affiliate::first()->code);
        });

        // Verify Tenant 2 cannot see Tenant 1's affiliate
        $tenant2->run(function () {
            Affiliate::query()->delete();
            $this->assertEquals(0, Affiliate::where('code', 'AHMED10')->count());

            // Create independent affiliate in Tenant 2
            Affiliate::create([
                'name' => 'Marketer Sara',
                'code' => 'SARA20',
                'commission_rate' => 20,
            ]);

            $this->assertEquals(1, Affiliate::count());
            $this->assertEquals(0, Affiliate::where('code', 'AHMED10')->count());
        });

        // Re-verify Tenant 1 cannot see Tenant 2's affiliate
        $tenant1->run(function () {
            $this->assertEquals(0, Affiliate::where('code', 'SARA20')->count());
            $this->assertEquals(1, Affiliate::count());
        });
    }
}
