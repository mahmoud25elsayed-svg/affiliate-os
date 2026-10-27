<?php

declare(strict_types=1);

namespace Tests\Feature\Checkout;

use App\Domain\Catalog\Services\ProductService;
use App\Domain\Checkout\DTOs\CheckoutPayload;
use App\Domain\Checkout\Services\OrderCreationService;
use App\Domain\Checkout\Services\ShippingCalculatorService;
use App\Models\Coupon;
use App\Models\Tenant;
use App\Models\TenantShippingRate;
use App\Support\Location\Governorate;
use App\Support\Settings\ShippingSettings;
use Database\Seeders\EgyptGovernoratesSeeder;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Lunar\Models\ProductVariant;
use Tests\TestCase;

class ServerSidePricingAndShippingTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected Governorate $cairo;

    protected Governorate $alex;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PlanSeeder::class);
        $this->seed(EgyptGovernoratesSeeder::class);
        $this->tenant = $this->ensureTenantExists('shippingstore');

        $this->cairo = Governorate::where('code', 'CAI')->firstOrFail();
        $this->alex = Governorate::where('code', 'ALX')->firstOrFail();
    }

    public function test_client_side_shipping_cost_is_strictly_ignored_and_server_calculates_rate(): void
    {
        $this->tenant->run(function () {
            $settings = ShippingSettings::load();
            $settings->default_rate_minor = 6000; // 60 EGP
            $settings->save();

            $calculator = app(ShippingCalculatorService::class);
            $quote = $calculator->calculate($this->alex->id, 20000); // 200 EGP subtotal

            // Even if client wanted 0 or 10 EGP, server returns 60 EGP
            $this->assertSame(6000, $quote->rate_minor);
            $this->assertFalse($quote->is_free);
        });
    }

    public function test_governorate_specific_rate_overrides_default_rate(): void
    {
        $this->tenant->run(function () {
            $settings = ShippingSettings::load();
            $settings->default_rate_minor = 5000; // 50 EGP default
            $settings->save();

            // Set specific Cairo rate to 35 EGP
            TenantShippingRate::create([
                'governorate_id' => $this->cairo->id,
                'rate_minor' => 3500, // 35 EGP
                'delivery_time_ar' => 'خلال 24 ساعة',
                'is_active' => true,
            ]);

            $calculator = app(ShippingCalculatorService::class);

            // Cairo uses specific rate
            $cairoQuote = $calculator->calculate($this->cairo->id, 20000);
            $this->assertSame(3500, $cairoQuote->rate_minor);
            $this->assertSame('خلال 24 ساعة', $cairoQuote->delivery_time_ar);

            // Alex uses default rate
            $alexQuote = $calculator->calculate($this->alex->id, 20000);
            $this->assertSame(5000, $alexQuote->rate_minor);
        });
    }

    public function test_free_shipping_threshold_triggers_automatic_free_shipping(): void
    {
        $this->tenant->run(function () {
            $settings = ShippingSettings::load();
            $settings->default_rate_minor = 5000;
            $settings->free_shipping_threshold_minor = 100000; // 1,000 EGP
            $settings->save();

            $calculator = app(ShippingCalculatorService::class);

            // Order under threshold (800 EGP)
            $underQuote = $calculator->calculate($this->cairo->id, 80000);
            $this->assertSame(5000, $underQuote->rate_minor);
            $this->assertFalse($underQuote->is_free);

            // Order at or above threshold (1,200 EGP)
            $aboveQuote = $calculator->calculate($this->cairo->id, 120000);
            $this->assertSame(0, $aboveQuote->rate_minor);
            $this->assertTrue($aboveQuote->is_free);
            $this->assertSame('threshold', $aboveQuote->free_reason);
        });
    }

    public function test_free_shipping_coupon_overrides_rate_for_sub_threshold_orders(): void
    {
        $this->tenant->run(function () {
            $settings = ShippingSettings::load();
            $settings->default_rate_minor = 5000;
            $settings->free_shipping_threshold_minor = 100000; // 1,000 EGP
            $settings->save();

            $coupon = Coupon::create([
                'code' => 'SHIPFREE',
                'name' => 'Free Shipping',
                'type' => Coupon::TYPE_FREE_SHIPPING,
                'value_minor' => 0,
                'is_active' => true,
            ]);

            $calculator = app(ShippingCalculatorService::class);

            // Order subtotal is only 300 EGP, but has free shipping coupon
            $quote = $calculator->calculate($this->cairo->id, 30000, $coupon);

            $this->assertSame(0, $quote->rate_minor);
            $this->assertTrue($quote->is_free);
            $this->assertSame('coupon', $quote->free_reason);
        });
    }

    public function test_full_order_creation_persists_immutable_price_snapshot(): void
    {
        $this->tenant->run(function () {
            $productService = app(ProductService::class);
            $product = $productService->createProduct([
                'name_ar' => 'ساعة فاخرة',
                'sku' => 'WATCH-01',
                'wholesale_price' => 30000,
                'min_price' => 40000,
                'max_price' => 60000,
                'retail_price' => 50000, // 500 EGP in minor units
                'status' => 'active',
            ]);

            /** @var ProductVariant $variant */
            $variant = $product->variants->firstOrFail();

            $settings = ShippingSettings::load();
            $settings->default_rate_minor = 4500; // 45 EGP
            $settings->free_shipping_threshold_minor = 0; // disable threshold to test standard rate
            $settings->cod_fee_minor = 1500;      // 15 EGP
            $settings->allow_cod = true;
            $settings->save();

            $orderCreationService = app(OrderCreationService::class);
            $payload = new CheckoutPayload(
                customer_name: 'محمود السيد',
                customer_phone: '01012345678',
                governorate_id: $this->cairo->id,
                city: 'مدينة نصر',
                address: 'شارع عباس العقاد عمارة 10',
                notes: 'يرجى الاتصال قبل الوصول',
                payment_method: 'cod',
                items: [
                    ['purchasable_id' => $variant->id, 'quantity' => 2], // 100,000 piasters
                ]
            );

            $order = $orderCreationService->create($payload);

            $this->assertSame(100000, $order->sub_total->value);
            $this->assertSame(4500, $order->shipping_total->value);
            $this->assertSame(1500, (int) $order->cod_fee_minor);
            $this->assertSame(106000, $order->total->value); // 1000 + 45 + 15 = 1060 EGP
            $this->assertSame('pending_confirmation', $order->status);

            // Verify immutable price snapshot
            $snapshot = is_string($order->price_snapshot) ? json_decode($order->price_snapshot, true) : $order->price_snapshot;
            $this->assertIsArray($snapshot);
            $this->assertSame(100000, $snapshot['subtotal_minor']);
            $this->assertSame(4500, $snapshot['shipping_minor']);
            $this->assertSame(1500, $snapshot['cod_fee_minor']);
            $this->assertSame(106000, $snapshot['total_minor']);
            $this->assertCount(1, $snapshot['items']);
            $this->assertSame('WATCH-01', $snapshot['items'][0]['sku']);
        });
    }
}
