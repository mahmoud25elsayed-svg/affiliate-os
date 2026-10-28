<?php

declare(strict_types=1);

use App\Domain\Checkout\Services\StorefrontCartService;
use App\Domain\Promotions\Services\AbandonedCartService;
use App\Domain\Promotions\Services\NewsletterService;
use App\Http\Controllers\Affiliate\AffiliateAuthController;
use App\Http\Controllers\Affiliate\AffiliateCatalogController;
use App\Http\Controllers\Affiliate\AffiliateDashboardController;
use App\Http\Controllers\Affiliate\AffiliateKycController;
use App\Http\Controllers\Affiliate\AffiliateOrderController;
use App\Http\Controllers\Affiliate\AffiliatePayoutController;
use App\Http\Controllers\Store\AffiliateKycDocumentController;
use App\Http\Controllers\Store\OrderDocumentController;
use App\Http\Controllers\Storefront\CategoryController;
use App\Http\Controllers\Storefront\CheckoutSuccessController;
use App\Http\Controllers\Storefront\HomeController;
use App\Http\Controllers\Storefront\PageController;
use App\Http\Controllers\Storefront\ProductController;
use App\Http\Controllers\Storefront\RobotsController;
use App\Http\Controllers\Storefront\SearchController;
use App\Http\Controllers\Storefront\SitemapController;
use App\Http\Middleware\EnsureAffiliateAuthenticated;
use App\Http\Middleware\EnsureCustomerAuthenticated;
use App\Http\Middleware\EnsureStorefrontActive;
use App\Livewire\Storefront\CartPage;
use App\Livewire\Storefront\CheckoutPage;
use App\Livewire\Storefront\CustomerAddressesPage;
use App\Livewire\Storefront\CustomerLoginPage;
use App\Livewire\Storefront\CustomerOrderDetailsPage;
use App\Livewire\Storefront\CustomerOrdersPage;
use App\Livewire\Storefront\CustomerProfilePage;
use App\Livewire\Storefront\CustomerRegisterPage;
use App\Livewire\Storefront\CustomerWishlistPage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

Route::middleware([
    'web',
    InitializeTenancyByDomain::class,
    PreventAccessFromCentralDomains::class,
    EnsureStorefrontActive::class,
])->group(function () {
    // Storefront Public Routes
    Route::get('/', HomeController::class)->name('storefront.home');
    Route::get('/products', [CategoryController::class, 'index'])->name('storefront.products.index');
    Route::get('/categories/{id}', [CategoryController::class, 'show'])->name('storefront.categories.show');
    Route::get('/products/{id}', [ProductController::class, 'show'])->name('storefront.products.show');
    Route::get('/search', SearchController::class)->name('storefront.search');

    // Cart & Checkout Routes (P09)
    Route::get('/cart', CartPage::class)->name('storefront.cart');
    Route::get('/checkout', CheckoutPage::class)->name('storefront.checkout');
    Route::get('/checkout/success/{reference}', CheckoutSuccessController::class)->name('storefront.checkout.success');
    Route::get('/cart/recover/{token}', function (string $token, AbandonedCartService $cartService, StorefrontCartService $storefrontCart) {
        $recovered = $cartService->recoverByToken($token);
        if ($recovered && is_array($recovered->cart_data) && ! empty($recovered->cart_data['raw_cart'])) {
            /** @var array<int, int> $rawCart */
            $rawCart = $recovered->cart_data['raw_cart'];
            foreach ($rawCart as $variantId => $qty) {
                $storefrontCart->updateQuantity((int) $variantId, (int) $qty);
            }
            session()->flash('success', 'تم استرجاع سلة مشترياتك بنجاح! يمكنك الآن إتمام طلبك.');

            return redirect()->route('storefront.checkout');
        }

        return redirect()->route('storefront.cart');
    })->name('storefront.cart.recover');

    // Newsletter Subscription (P14)
    Route::post('/newsletter/subscribe', function (Request $request, NewsletterService $newsletterService) {
        $data = $request->validate([
            'email' => 'required|email|max:255',
            'name' => 'nullable|string|max:100',
        ]);

        $result = $newsletterService->subscribe($data['email'], $data['name'] ?? null);

        if ($request->wantsJson()) {
            return response()->json($result);
        }

        return back()->with('newsletter_message', $result['message']);
    })->name('storefront.newsletter.subscribe');

    // Customer Authentication & Accounts (P10)
    Route::get('/login', CustomerLoginPage::class)->name('storefront.login');
    Route::get('/register', CustomerRegisterPage::class)->name('storefront.register');
    Route::post('/logout', function () {
        Auth::guard('customer')->logout();

        return redirect()->route('storefront.home');
    })->name('storefront.logout');

    Route::middleware([EnsureCustomerAuthenticated::class])->prefix('account')->group(function () {
        Route::get('/', fn () => redirect()->route('storefront.account.orders'))->name('storefront.account');
        Route::get('/orders', CustomerOrdersPage::class)->name('storefront.account.orders');
        Route::get('/orders/{reference}', CustomerOrderDetailsPage::class)->name('storefront.account.orders.show');
        Route::get('/addresses', CustomerAddressesPage::class)->name('storefront.account.addresses');
        Route::get('/wishlist', CustomerWishlistPage::class)->name('storefront.account.wishlist');
        Route::get('/profile', CustomerProfilePage::class)->name('storefront.account.profile');
    });

    // Static Informational Pages
    Route::get('/about', [PageController::class, 'about'])->name('storefront.pages.about');
    Route::get('/terms', [PageController::class, 'terms'])->name('storefront.pages.terms');
    Route::get('/privacy', [PageController::class, 'privacy'])->name('storefront.pages.privacy');
    Route::get('/shipping', [PageController::class, 'shipping'])->name('storefront.pages.shipping');

    // SEO Routes
    Route::get('/sitemap.xml', SitemapController::class)->name('storefront.sitemap');
    Route::get('/robots.txt', RobotsController::class)->name('storefront.robots');

    // Order Documents (Printable Invoices & Waybills - P11)
    Route::get('/orders/{reference}/invoice', [OrderDocumentController::class, 'invoice'])->name('store.orders.invoice');
    Route::get('/orders/{reference}/waybill', [OrderDocumentController::class, 'waybill'])->name('store.orders.waybill');

    // KYC Document Secure View (P16)
    Route::get('/store/affiliates/{affiliate}/kyc/{side}', [AffiliateKycDocumentController::class, 'show'])
        ->middleware('auth')
        ->name('store.affiliates.kyc-document');

    // Affiliate Portal Routes (P16)
    Route::prefix('partners')->group(function () {
        Route::get('/join', [AffiliateAuthController::class, 'showJoin'])->name('affiliate.join');
        Route::post('/register', [AffiliateAuthController::class, 'register'])->name('affiliate.register');
        Route::get('/login', [AffiliateAuthController::class, 'showLogin'])->name('affiliate.login');
        Route::post('/login', [AffiliateAuthController::class, 'login'])->name('affiliate.login.submit');
        Route::get('/verify-otp', [AffiliateAuthController::class, 'showVerifyOtp'])->name('affiliate.verify-otp.show');
        Route::post('/verify-otp', [AffiliateAuthController::class, 'verifyOtp'])->name('affiliate.verify-otp.submit');

        Route::middleware(EnsureAffiliateAuthenticated::class)->group(function () {
            Route::post('/logout', [AffiliateAuthController::class, 'logout'])->name('affiliate.logout');
            Route::get('/dashboard', [AffiliateDashboardController::class, 'index'])->name('affiliate.dashboard');
            Route::get('/kyc', [AffiliateKycController::class, 'show'])->name('affiliate.kyc.show');
            Route::post('/kyc', [AffiliateKycController::class, 'submit'])->name('affiliate.kyc.submit');
            Route::get('/payout-settings', [AffiliatePayoutController::class, 'show'])->name('affiliate.payout-settings.show');
            Route::post('/payout-settings/request-otp', [AffiliatePayoutController::class, 'requestOtp'])->name('affiliate.payout-settings.request-otp');
            Route::post('/payout-settings', [AffiliatePayoutController::class, 'update'])->name('affiliate.payout-settings.update');

            // Affiliate Catalog & Direct Orders (P17)
            Route::get('/catalog', [AffiliateCatalogController::class, 'index'])->name('affiliate.catalog.index');
            Route::get('/orders', [AffiliateOrderController::class, 'index'])->name('affiliate.orders.index');
            Route::get('/orders/create', [AffiliateOrderController::class, 'create'])->name('affiliate.orders.create');
            Route::post('/orders/quote', [AffiliateOrderController::class, 'quote'])->name('affiliate.orders.quote');
            Route::post('/orders', [AffiliateOrderController::class, 'store'])->name('affiliate.orders.store');
            Route::get('/orders/import-csv', [AffiliateOrderController::class, 'showImportCsv'])->name('affiliate.orders.import-csv.show');
            Route::post('/orders/import-csv', [AffiliateOrderController::class, 'importCsv'])->name('affiliate.orders.import-csv.submit');
            Route::get('/orders/{reference}', [AffiliateOrderController::class, 'show'])->name('affiliate.orders.show');
            Route::post('/orders/{reference}/cancel', [AffiliateOrderController::class, 'cancel'])->name('affiliate.orders.cancel');
        });
    });

    // Health check endpoint from P06
    Route::get('/storefront', function () {
        return 'Storefront Active';
    });
});
