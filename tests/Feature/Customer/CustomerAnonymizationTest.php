<?php

declare(strict_types=1);

namespace Tests\Feature\Customer;

use App\Domain\Customer\Services\CustomerAnonymizationService;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\Tenant;
use App\Support\Location\Governorate;
use Database\Seeders\EgyptGovernoratesSeeder;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Lunar\Base\ValueObjects\Cart\TaxBreakdown;
use Lunar\Models\Channel;
use Lunar\Models\Order;
use Lunar\Models\OrderAddress;
use Lunar\Models\OrderLine;
use Tests\TestCase;

class CustomerAnonymizationTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PlanSeeder::class);
        $this->seed(EgyptGovernoratesSeeder::class);
        $this->tenant = $this->ensureTenantExists('anonstore');
    }

    public function test_account_deletion_scrubs_pii_and_strictly_preserves_financial_records(): void
    {
        $this->tenant->run(function () {
            // 1. Create a customer with addresses, wishlists, and orders
            $customer = Customer::create([
                'tenant_id' => $this->tenant->id,
                'first_name' => 'خالد',
                'last_name' => 'إبراهيم',
                'email' => 'khaled@example.com',
                'phone_e164' => '+201012345678',
                'phone_hash' => hash('sha256', '+201012345678'),
                'password' => 'secret_hash',
                'is_active' => true,
            ]);

            $cairo = Governorate::where('code', 'CAI')->firstOrFail();

            CustomerAddress::create([
                'tenant_id' => $this->tenant->id,
                'customer_id' => $customer->id,
                'name' => 'خالد إبراهيم',
                'phone' => '01012345678',
                'governorate_id' => $cairo->id,
                'city' => 'مدينة نصر',
                'address_line' => 'شارع عباس العقاد',
                'is_default' => true,
            ]);

            $channel = Channel::first() ?? Channel::create(['name' => 'Default', 'handle' => 'default', 'default' => true]);

            // Create Financial Order
            $order = Order::create([
                'storefront_customer_id' => $customer->id,
                'channel_id' => $channel->id,
                'status' => 'delivered',
                'reference' => 'ORD-26-ANON01',
                'customer_reference' => 'ORD-26-ANON01',
                'sub_total' => 150000, // 1500 EGP
                'discount_total' => 15000, // 150 EGP
                'shipping_total' => 5000,  // 50 EGP
                'tax_breakdown' => new TaxBreakdown,
                'tax_total' => 0,
                'total' => 140000,        // 1400 EGP
                'notes' => 'يرجى الاتصال قبل الوصول برقم 01012345678',
                'customer_phone_e164' => '+201012345678',
                'customer_phone_hash' => $customer->phone_hash,
                'currency_code' => 'EGP',
                'compare_currency_code' => 'EGP',
            ]);

            OrderAddress::create([
                'order_id' => $order->id,
                'type' => 'shipping',
                'first_name' => 'خالد',
                'last_name' => 'إبراهيم',
                'contact_phone' => '01012345678',
                'contact_email' => 'khaled@example.com',
                'line_one' => 'شارع عباس العقاد، عمارة 4',
                'city' => 'مدينة نصر',
            ]);

            OrderLine::create([
                'order_id' => $order->id,
                'purchasable_type' => 'Lunar\Models\ProductVariant',
                'purchasable_id' => 1,
                'type' => 'physical',
                'description' => 'منتج تجريبي فاخر',
                'identifier' => 'SKU-TEST-1',
                'unit_price' => 150000,
                'unit_quantity' => 1,
                'quantity' => 1,
                'sub_total' => 150000,
                'discount_total' => 15000,
                'tax_breakdown' => new TaxBreakdown,
                'tax_total' => 0,
                'total' => 135000,
            ]);

            Auth::guard('customer')->login($customer);
            $this->assertTrue(Auth::guard('customer')->check());

            // 2. Execute Anonymization
            $anonymizer = app(CustomerAnonymizationService::class);
            $anonymizer->anonymize($customer);

            // 3. Verify Customer Data Scrubbing
            $freshCustomer = Customer::withTrashed()->find($customer->id);
            $this->assertNotNull($freshCustomer);
            $this->assertTrue($freshCustomer->trashed()); // Soft-deleted
            $this->assertSame('عميل محذوف', $freshCustomer->first_name);
            $this->assertNull($freshCustomer->last_name);
            $this->assertNull($freshCustomer->email);
            $this->assertNull($freshCustomer->phone_e164);
            $this->assertNull($freshCustomer->password);
            $this->assertNotNull($freshCustomer->anonymized_at);
            $this->assertFalse($freshCustomer->is_active);

            // Customer addresses deleted
            $this->assertSame(0, CustomerAddress::where('customer_id', $customer->id)->count());

            // Customer logged out
            $this->assertFalse(Auth::guard('customer')->check());

            // 4. Verify Financial Integrity of Order (STRICTLY PRESERVED)
            $freshOrder = Order::find($order->id);
            $this->assertNotNull($freshOrder);
            $this->assertSame(150000, $freshOrder->sub_total->value);
            $this->assertSame(15000, $freshOrder->discount_total->value);
            $this->assertSame(5000, $freshOrder->shipping_total->value);
            $this->assertSame(140000, $freshOrder->total->value);
            $this->assertSame('ORD-26-ANON01', $freshOrder->reference);

            // Customer PII on order is redacted
            $this->assertNull($freshOrder->customer_phone_e164);
            $this->assertNull($freshOrder->notes);

            $orderAddress = OrderAddress::where('order_id', $order->id)->first();
            $this->assertNotNull($orderAddress);
            $this->assertSame('عميل', $orderAddress->first_name);
            $this->assertSame('محذوف', $orderAddress->last_name);
            $this->assertNull($orderAddress->contact_phone);
            $this->assertNull($orderAddress->contact_email);
            $this->assertSame('عنوان محذوف لخصوصية العميل', $orderAddress->line_one);
        });
    }
}
