<?php

declare(strict_types=1);

use App\Http\Controllers\Storefront\CategoryController;
use App\Http\Controllers\Storefront\CheckoutSuccessController;
use App\Http\Controllers\Storefront\HomeController;
use App\Http\Controllers\Storefront\PageController;
use App\Http\Controllers\Storefront\ProductController;
use App\Http\Controllers\Storefront\RobotsController;
use App\Http\Controllers\Storefront\SearchController;
use App\Http\Controllers\Storefront\SitemapController;
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

    // Health check endpoint from P06
    Route::get('/storefront', function () {
        return 'Storefront Active';
    });
});
