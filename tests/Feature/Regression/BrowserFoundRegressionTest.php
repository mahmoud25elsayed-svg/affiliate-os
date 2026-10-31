<?php

declare(strict_types=1);

namespace Tests\Feature\Regression;

use App\Domain\Affiliate\Services\AffiliateTrackingService;
use App\Domain\Catalog\Services\ProductService;
use App\Domain\Checkout\Services\StorefrontCartService;
use App\Http\Middleware\InitializeTenancyForLivewire;
use App\Models\Affiliate;
use App\Models\InventoryBalance;
use App\Models\Tenant;
use App\Models\TrackingLink;
use App\Models\User;
use App\Support\Money;
use Database\Seeders\PilotStagingSeeder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Lunar\Models\Order;
use Lunar\Models\ProductVariant;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

/**
 * Bugs found by driving the app in a real browser that the HTTP-level suite did not catch.
 */
class BrowserFoundRegressionTest extends TestCase
{
    private Tenant $tenant;

    private User $owner;

    private string $host = 'http://cairo-electronics.localhost';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PilotStagingSeeder::class);
        $this->tenant = Tenant::findOrFail('cairo-electronics');
        $this->owner = User::where('email', 'tarek@cairo-electronics.com')->firstOrFail();
    }

    public function test_store_panel_pages_backed_by_lunar_models_render(): void
    {
        // Filament tenancy needs a `tenant` relationship on Lunar models; without it these were 500s.
        $order = $this->tenant->run(fn () => Order::query()->firstOrFail());

        foreach (['products', 'products/create', 'orders', 'orders/'.$order->id, 'brands', 'collections', 'inventory-balances', 'manage-plan-and-usage'] as $page) {
            $this->actingAs($this->owner)
                ->get("{$this->host}/store/cairo-electronics/{$page}")
                ->assertOk();
        }
    }

    public function test_store_panel_links_to_standalone_merchant_tools(): void
    {
        $this->actingAs($this->owner)
            ->get("{$this->host}/store/cairo-electronics")
            ->assertOk()
            ->assertSee('/store/call-center', false)
            ->assertSee('/store/shipments', false)
            ->assertSee('/store/reports', false)
            ->assertDontSee('/tenancy/assets/', false);
    }

    public function test_every_html_page_allows_alpine_expression_evaluation(): void
    {
        $csp = (string) $this->get("{$this->host}/partners/login")->headers->get('Content-Security-Policy');

        $this->assertStringContainsString("'unsafe-eval'", $csp);
    }

    public function test_livewire_requests_on_a_store_domain_run_inside_that_tenant(): void
    {
        $this->assertContains(
            InitializeTenancyForLivewire::class,
            Route::getRoutes()->match(Request::create('/livewire/update', 'POST'))->gatherMiddleware()
        );

        $seen = null;
        app(InitializeTenancyForLivewire::class)->handle(
            Request::create("{$this->host}/livewire/update", 'POST'),
            function () use (&$seen): Response {
                $seen = tenant('id');

                return new Response;
            }
        );

        $this->assertSame('cairo-electronics', $seen);
    }

    public function test_tracking_link_price_is_shown_in_cart_not_only_charged(): void
    {
        $this->tenant->run(function (): void {
            $variant = ProductVariant::query()->where('sku', 'SMARTWATCH-V2')->firstOrFail();
            $link = TrackingLink::create([
                'tenant_id' => $this->tenant->id,
                'affiliate_id' => Affiliate::query()->firstOrFail()->id,
                'product_id' => $variant->product_id,
                'code' => 'REGR520',
                'title' => 'regression',
                'custom_selling_price_minor' => 52000,
                'is_active' => true,
            ]);

            $tracking = app(AffiliateTrackingService::class);
            request()->cookies->set(AffiliateTrackingService::COOKIE_NAME, $tracking->generateTokenPayload($link));

            $cart = app(StorefrontCartService::class);
            $cart->clear();
            $cart->addItem((int) $variant->id, 1);

            $this->assertSame(52000, $cart->getItems()[0]['unit_price_minor']);
            $this->assertSame(52000, $tracking->linkedPriceMinor($variant));
        });
    }

    public function test_opening_stock_lands_in_the_warehouse_so_it_can_be_reserved(): void
    {
        $this->actingAs($this->owner);

        $this->tenant->run(function (): void {
            $product = app(ProductService::class)->createProduct([
                'name_ar' => 'منتج بمخزون افتتاحي',
                'sku' => 'OPEN-STOCK-1',
                'retail_price' => Money::fromDecimalString('300')->amount,
                'wholesale_price' => 15000,
                'min_price' => 25000,
                'max_price' => 40000,
                'opening_stock' => 12,
            ]);

            $variant = ProductVariant::query()->where('product_id', $product->id)->firstOrFail();
            $this->assertSame(12, (int) InventoryBalance::where('product_variant_id', $variant->id)->sum('on_hand'));
            $this->assertSame(12, (int) $variant->fresh()?->stock);
        });
    }

    public function test_money_minor_of_accepts_lunar_price_objects_and_raw_ints(): void
    {
        $order = $this->tenant->run(fn () => Order::query()->firstOrFail());

        $this->assertIsObject($order->total);
        $this->assertSame((int) $order->total->value, Money::minorOf($order->total));
        $this->assertSame(1250, Money::minorOf(1250));
        $this->assertSame(0, Money::minorOf(null));
    }
}
