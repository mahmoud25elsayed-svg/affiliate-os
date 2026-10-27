<?php

declare(strict_types=1);

namespace Tests\Feature\Customer;

use App\Domain\Catalog\Services\ProductService;
use App\Livewire\Storefront\CustomerAddressesPage;
use App\Livewire\Storefront\CustomerOrderDetailsPage;
use App\Livewire\Storefront\CustomerOrdersPage;
use App\Livewire\Storefront\CustomerProfilePage;
use App\Livewire\Storefront\CustomerWishlistPage;
use App\Livewire\Storefront\WishlistButton;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\Tenant;
use App\Support\Location\Governorate;
use Database\Seeders\EgyptGovernoratesSeeder;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Lunar\Base\ValueObjects\Cart\TaxBreakdown;
use Lunar\Models\Channel;
use Lunar\Models\Order;
use Lunar\Models\Product;
use Tests\TestCase;

class CustomerAccountManagementTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected Customer $customer;

    protected Governorate $cairo;

    protected Governorate $alex;

    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        app()->setLocale('ar');
        $this->seed(PlanSeeder::class);
        $this->seed(EgyptGovernoratesSeeder::class);
        $this->tenant = $this->ensureTenantExists('mgmtstore');

        $this->cairo = Governorate::where('code', 'CAI')->firstOrFail();
        $this->alex = Governorate::where('code', 'ALX')->firstOrFail();

        $this->tenant->run(function () {
            $this->customer = Customer::create([
                'tenant_id' => $this->tenant->id,
                'first_name' => 'طارق',
                'last_name' => 'سعيد',
                'email' => 'tarek@example.com',
                'phone_e164' => '+201011112222',
                'phone_hash' => hash('sha256', '+201011112222'),
                'password' => Hash::make('old-password'),
                'is_active' => true,
            ]);

            $productService = app(ProductService::class);
            $this->product = $productService->createProduct([
                'name_ar' => 'ساعة يد ذكية',
                'sku' => 'WATCH-01',
                'wholesale_price' => 50000,
                'min_price' => 70000,
                'max_price' => 100000,
                'retail_price' => 85000,
                'status' => 'active',
            ]);
        });
    }

    public function test_customer_can_manage_addresses_and_set_default(): void
    {
        $this->tenant->run(function () {
            Auth::guard('customer')->login($this->customer);

            // 1. Add first address (should automatically become default)
            Livewire::test(CustomerAddressesPage::class)
                ->set('name', 'طارق سعيد')
                ->set('phone', '01011112222')
                ->set('governorate_id', $this->cairo->id)
                ->set('city', 'المعادي')
                ->set('address_line', 'شارع النصر، عمارة 10')
                ->call('saveAddress')
                ->assertSee('تمت إضافة العنوان بنجاح');

            $this->assertDatabaseHas('customer_addresses', [
                'customer_id' => $this->customer->id,
                'city' => 'المعادي',
                'is_default' => true,
            ]);

            $addr1 = CustomerAddress::where('city', 'المعادي')->firstOrFail();

            // 2. Add second address and mark as default
            Livewire::test(CustomerAddressesPage::class)
                ->set('name', 'طارق سعيد (العمل)')
                ->set('phone', '01011112222')
                ->set('governorate_id', $this->alex->id)
                ->set('city', 'سموحة')
                ->set('address_line', 'شارع فوزي معاذ')
                ->set('is_default', true)
                ->call('saveAddress');

            $addr2 = CustomerAddress::where('city', 'سموحة')->firstOrFail();
            $this->assertTrue($addr2->fresh()->is_default);
            $this->assertFalse($addr1->fresh()->is_default); // First address is no longer default

            // 3. Set first address back as default
            Livewire::test(CustomerAddressesPage::class)
                ->call('setAsDefault', $addr1->id);

            $this->assertTrue($addr1->fresh()->is_default);
            $this->assertFalse($addr2->fresh()->is_default);

            // 4. Delete second address
            Livewire::test(CustomerAddressesPage::class)
                ->call('deleteAddress', $addr2->id);

            $this->assertDatabaseMissing('customer_addresses', ['id' => $addr2->id]);
        });
    }

    public function test_customer_wishlist_toggle_and_management(): void
    {
        $this->tenant->run(function () {
            Auth::guard('customer')->login($this->customer);

            // Toggle wishlist button on product
            Livewire::test(WishlistButton::class, ['productId' => $this->product->id])
                ->assertSet('isWishlisted', false)
                ->call('toggle')
                ->assertSet('isWishlisted', true);

            $this->assertDatabaseHas('customer_wishlists', [
                'customer_id' => $this->customer->id,
                'product_id' => $this->product->id,
            ]);

            // Wishlist page lists item
            Livewire::test(CustomerWishlistPage::class)
                ->assertSee('ساعة يد ذكية')
                ->assertSee('850.00 ج.م');

            // Remove from wishlist page
            $wishlist = $this->customer->wishlists()->firstOrFail();
            Livewire::test(CustomerWishlistPage::class)
                ->call('removeFromWishlist', $wishlist->id)
                ->assertSee('تمت إزالة المنتج');

            $this->assertDatabaseMissing('customer_wishlists', ['id' => $wishlist->id]);
        });
    }

    public function test_customer_orders_listing_and_details_authorization(): void
    {
        $this->tenant->run(function () {
            $channel = Channel::first() ?? Channel::create(['name' => 'Default', 'handle' => 'default', 'default' => true]);

            // Create an order for this customer
            $myOrder = Order::create([
                'storefront_customer_id' => $this->customer->id,
                'channel_id' => $channel->id,
                'status' => 'pending_confirmation',
                'reference' => 'ORD-26-MYTEST',
                'customer_reference' => 'ORD-26-MYTEST',
                'sub_total' => 85000,
                'discount_total' => 0,
                'shipping_total' => 4500,
                'tax_breakdown' => new TaxBreakdown,
                'tax_total' => 0,
                'total' => 89500,
                'currency_code' => 'EGP',
                'compare_currency_code' => 'EGP',
            ]);

            // Create an order for another customer
            $otherCustomer = Customer::create([
                'tenant_id' => $this->tenant->id,
                'first_name' => 'عميل آخر',
                'phone_e164' => '+201099990000',
                'phone_hash' => hash('sha256', '+201099990000'),
                'is_active' => true,
            ]);

            $otherOrder = Order::create([
                'storefront_customer_id' => $otherCustomer->id,
                'channel_id' => $channel->id,
                'status' => 'pending_confirmation',
                'reference' => 'ORD-26-OTHER1',
                'customer_reference' => 'ORD-26-OTHER1',
                'sub_total' => 10000,
                'discount_total' => 0,
                'shipping_total' => 4500,
                'tax_breakdown' => new TaxBreakdown,
                'tax_total' => 0,
                'total' => 14500,
                'currency_code' => 'EGP',
                'compare_currency_code' => 'EGP',
            ]);

            Auth::guard('customer')->login($this->customer);

            // My orders list must show myOrder and not otherOrder
            Livewire::test(CustomerOrdersPage::class)
                ->assertSee('ORD-26-MYTEST')
                ->assertDontSee('ORD-26-OTHER1');

            // Can view my order details
            Livewire::test(CustomerOrderDetailsPage::class, ['reference' => 'ORD-26-MYTEST'])
                ->assertSee('ORD-26-MYTEST')
                ->assertSee('895.00 ج.م');

            // Viewing other customer's order triggers 403 Forbidden
            Livewire::test(CustomerOrderDetailsPage::class, ['reference' => 'ORD-26-OTHER1'])
                ->assertForbidden();
        });
    }

    public function test_customer_can_update_profile_and_password(): void
    {
        $this->tenant->run(function () {
            Auth::guard('customer')->login($this->customer);

            // Profile update
            Livewire::test(CustomerProfilePage::class)
                ->set('first_name', 'طارق المعدل')
                ->set('last_name', 'سعيد الجديد')
                ->set('email', 'tarek.new@example.com')
                ->call('updateProfile')
                ->assertSee('تم تحديث البيانات الشخصية بنجاح');

            $this->assertSame('طارق المعدل', $this->customer->fresh()->first_name);
            $this->assertSame('tarek.new@example.com', $this->customer->fresh()->email);

            // Password update with invalid current password fails
            Livewire::test(CustomerProfilePage::class)
                ->set('current_password', 'wrong-pass')
                ->set('new_password', 'new-secret-123')
                ->set('new_password_confirmation', 'new-secret-123')
                ->call('updatePassword')
                ->assertHasErrors(['current_password']);

            // Password update with correct current password succeeds
            Livewire::test(CustomerProfilePage::class)
                ->set('current_password', 'old-password')
                ->set('new_password', 'new-secret-123')
                ->set('new_password_confirmation', 'new-secret-123')
                ->call('updatePassword')
                ->assertSee('تم تغيير كلمة المرور بنجاح');

            $this->assertTrue(Hash::check('new-secret-123', $this->customer->fresh()->password));
        });
    }
}
