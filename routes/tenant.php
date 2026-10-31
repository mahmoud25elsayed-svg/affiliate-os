<?php

declare(strict_types=1);

use App\Domain\Checkout\Services\StorefrontCartService;
use App\Domain\Promotions\Services\AbandonedCartService;
use App\Domain\Promotions\Services\NewsletterService;
use App\Http\Controllers\Affiliate\AffiliateAiToolsController;
use App\Http\Controllers\Affiliate\AffiliateAuthController;
use App\Http\Controllers\Affiliate\AffiliateCatalogController;
use App\Http\Controllers\Affiliate\AffiliateDashboardController;
use App\Http\Controllers\Affiliate\AffiliateHelpCenterController;
use App\Http\Controllers\Affiliate\AffiliateKycController;
use App\Http\Controllers\Affiliate\AffiliateOrderController;
use App\Http\Controllers\Affiliate\AffiliatePayoutController;
use App\Http\Controllers\Affiliate\AffiliatePayoutRequestController;
use App\Http\Controllers\Affiliate\AffiliatePixelController;
use App\Http\Controllers\Affiliate\AffiliateSupportController;
use App\Http\Controllers\Affiliate\AffiliateTrackingLinkController;
use App\Http\Controllers\Affiliate\AffiliateWalletController;
use App\Http\Controllers\Affiliate\App\AppAnalyticsController;
use App\Http\Controllers\Affiliate\App\AppCatalogController;
use App\Http\Controllers\Affiliate\App\AppDashboardController;
use App\Http\Controllers\Affiliate\App\AppLevelsController;
use App\Http\Controllers\Affiliate\App\AppLinksController;
use App\Http\Controllers\Affiliate\App\AppOrderController;
use App\Http\Controllers\Affiliate\App\AppProfileController;
use App\Http\Controllers\Affiliate\App\AppWalletController;
use App\Http\Controllers\Api\OpenApiDocsController;
use App\Http\Controllers\Store\AffiliateKycDocumentController;
use App\Http\Controllers\Store\CourierWebhookController;
use App\Http\Controllers\Store\MerchantOnboardingWizardController;
use App\Http\Controllers\Store\OrderDocumentController;
use App\Http\Controllers\Store\StoreAffiliateSettingsController;
use App\Http\Controllers\Store\StoreAiToolsController;
use App\Http\Controllers\Store\StoreCallCenterController;
use App\Http\Controllers\Store\StoreCodRemittanceController;
use App\Http\Controllers\Store\StoreCustomerWalletController;
use App\Http\Controllers\Store\StoreDomainController;
use App\Http\Controllers\Store\StoreKpiDashboardController;
use App\Http\Controllers\Store\StorePayoutManagementController;
use App\Http\Controllers\Store\StorePixelTrackingController;
use App\Http\Controllers\Store\StoreReportController;
use App\Http\Controllers\Store\StoreShipmentController;
use App\Http\Controllers\Store\StoreSupplierController;
use App\Http\Controllers\Store\StoreSupportTicketController;
use App\Http\Controllers\Store\StoreThemeController;
use App\Http\Controllers\Storefront\AffiliateReferralController;
use App\Http\Controllers\Storefront\CategoryController;
use App\Http\Controllers\Storefront\CheckoutSuccessController;
use App\Http\Controllers\Storefront\EmbedOrderController;
use App\Http\Controllers\Storefront\HomeController;
use App\Http\Controllers\Storefront\PageController;
use App\Http\Controllers\Storefront\ProductController;
use App\Http\Controllers\Storefront\RobotsController;
use App\Http\Controllers\Storefront\SearchController;
use App\Http\Controllers\Storefront\SitemapController;
use App\Http\Controllers\Storefront\StoreHelpCenterController;
use App\Http\Middleware\EnsureAffiliateAuthenticated;
use App\Http\Middleware\EnsureCustomerAuthenticated;
use App\Http\Middleware\EnsureStorefrontActive;
use App\Http\Middleware\RedirectSubdomainToCustomDomain;
use App\Http\Middleware\SetLocale;
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
    RedirectSubdomainToCustomDomain::class,
    // Again after tenancy, so the store's default language applies.
    SetLocale::class,
])->group(function () {
    // Storefront Public Routes
    Route::get('/ref/{code}', AffiliateReferralController::class)->name('storefront.ref');

    Route::middleware(['storefront.cache'])->group(function () {
        Route::get('/', HomeController::class)->name('storefront.home');
        Route::get('/products', [CategoryController::class, 'index'])->name('storefront.products.index');
        Route::get('/categories/{id}', [CategoryController::class, 'show'])->name('storefront.categories.show');
        Route::get('/products/{id}', [ProductController::class, 'show'])->name('storefront.products.show');
        Route::get('/search', SearchController::class)->name('storefront.search');

        // Public Help Center & FAQ (P32)
        Route::get('/help', [StoreHelpCenterController::class, 'index'])->name('storefront.help.index');
        Route::get('/help/{slug}', [StoreHelpCenterController::class, 'show'])->name('storefront.help.show');
    });

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
    })->middleware('throttle:10,1')->name('storefront.newsletter.subscribe');

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
    // Legacy default navigation links already saved in stores' theme settings.
    Route::redirect('/about-us', '/about', 301);
    Route::redirect('/customer/orders', '/account/orders', 301);
    Route::get('/terms', [PageController::class, 'terms'])->name('storefront.pages.terms');
    Route::get('/privacy', [PageController::class, 'privacy'])->name('storefront.pages.privacy');
    Route::get('/shipping', [PageController::class, 'shipping'])->name('storefront.pages.shipping');
    Route::get('/pages/{slug}', [PageController::class, 'show'])->name('storefront.pages.show');

    // Developer API Documentation (P29)
    Route::get('/docs/api', [OpenApiDocsController::class, 'ui'])->name('docs.api');

    // Embeddable Order Form for Landing Pages (P29)
    Route::prefix('embed/order')->group(function () {
        Route::post('/quote', [EmbedOrderController::class, 'quote'])->middleware('throttle:60,1')->name('storefront.embed.quote');
        Route::get('/{product}', [EmbedOrderController::class, 'show'])->name('storefront.embed.show');
        Route::post('/{product}', [EmbedOrderController::class, 'store'])->middleware('throttle:10,1')->name('storefront.embed.store');
    });

    // SEO Routes
    Route::get('/sitemap.xml', SitemapController::class)->name('storefront.sitemap');
    Route::get('/robots.txt', RobotsController::class)->name('storefront.robots');

    // Order Documents (Printable Invoices & Waybills - P11)
    Route::get('/orders/{reference}/invoice', [OrderDocumentController::class, 'invoice'])->name('store.orders.invoice');
    Route::get('/orders/{reference}/waybill', [OrderDocumentController::class, 'waybill'])->name('store.orders.waybill');

    // KYC Document Secure View (P16)
    Route::get('/store/affiliates/{affiliate}/kyc/{side}', [AffiliateKycDocumentController::class, 'show'])
        ->middleware(['auth', 'tenant.user'])
        ->name('store.affiliates.kyc-document');

    // Store Admin Payouts & Affiliate Settings (P20)
    Route::middleware(['auth', 'tenant.user'])->prefix('store')->group(function () {
        // Merchant Onboarding & Demo Data (P38)
        Route::get('/onboarding/progress', [MerchantOnboardingWizardController::class, 'progress'])->name('store.onboarding.progress');
        Route::post('/demo-data/seed', [MerchantOnboardingWizardController::class, 'seedDemo'])->name('store.demo-data.seed');
        Route::post('/demo-data/purge', [MerchantOnboardingWizardController::class, 'purgeDemo'])->name('store.demo-data.purge');

        Route::get('/affiliates/settings', [StoreAffiliateSettingsController::class, 'show'])->name('store.affiliates.settings.show');
        Route::post('/affiliates/settings', [StoreAffiliateSettingsController::class, 'update'])->name('store.affiliates.settings.update');

        Route::get('/payouts', [StorePayoutManagementController::class, 'index'])->name('store.payouts.index');
        Route::post('/payouts/{payoutRequest}/approve', [StorePayoutManagementController::class, 'approve'])->name('store.payouts.approve');
        Route::post('/payouts/{payoutRequest}/execute', [StorePayoutManagementController::class, 'execute'])->name('store.payouts.execute');
        Route::post('/payouts/{payoutRequest}/reject', [StorePayoutManagementController::class, 'reject'])->name('store.payouts.reject');
        Route::post('/payouts/batches', [StorePayoutManagementController::class, 'createBatch'])->name('store.payouts.batches.create');
        Route::get('/payouts/batches/{batch}/export', [StorePayoutManagementController::class, 'exportBatch'])->name('store.payouts.batches.export');
        Route::post('/payouts/batches/{batch}/process', [StorePayoutManagementController::class, 'processBatch'])->name('store.payouts.batches.process');

        // Call Center & Confirmation Queue (P23)
        Route::prefix('call-center')->middleware('feature:call_center')->group(function () {
            Route::get('/', [StoreCallCenterController::class, 'index'])->name('store.call-center.index');
            Route::post('/claim', [StoreCallCenterController::class, 'claim'])->name('store.call-center.claim');
            Route::post('/orders/{order}/release', [StoreCallCenterController::class, 'release'])->name('store.call-center.release');
            Route::post('/orders/{order}/attempt', [StoreCallCenterController::class, 'attempt'])->name('store.call-center.attempt');
            Route::post('/orders/{order}/modify', [StoreCallCenterController::class, 'modify'])->name('store.call-center.modify');
            Route::get('/performance', [StoreCallCenterController::class, 'performance'])->name('store.call-center.performance');
        });

        // Shipments & Courier Fulfillment (P24)
        Route::prefix('shipments')->middleware('feature:courier')->group(function () {
            Route::get('/', [StoreShipmentController::class, 'index'])->name('store.shipments.index');
            Route::post('/orders/{order}/dispatch', [StoreShipmentController::class, 'dispatch'])->name('store.shipments.dispatch');
            Route::get('/{shipment}', [StoreShipmentController::class, 'show'])->name('store.shipments.show');
            Route::get('/{shipment}/waybill', [StoreShipmentController::class, 'waybill'])->name('store.shipments.waybill');
        });

        // COD Remittance & Reconciliation (P25)
        Route::prefix('cod-remittances')->middleware('feature:cod_reconciliation')->group(function () {
            Route::get('/', [StoreCodRemittanceController::class, 'index'])->name('store.cod.index');
            Route::get('/create', [StoreCodRemittanceController::class, 'create'])->name('store.cod.create');
            Route::post('/', [StoreCodRemittanceController::class, 'store'])->name('store.cod.store');
            Route::get('/{remittance}', [StoreCodRemittanceController::class, 'show'])->name('store.cod.show');
            Route::post('/{remittance}/settle', [StoreCodRemittanceController::class, 'settle'])->name('store.cod.settle');
        });

        // Customer Wallets & Loyalty Points (P26)
        Route::prefix('wallets')->middleware('feature:loyalty')->group(function () {
            Route::get('/', [StoreCustomerWalletController::class, 'index'])->name('store.wallets.index');
            Route::get('/{wallet}', [StoreCustomerWalletController::class, 'show'])->name('store.wallets.show');
            Route::post('/{wallet}/adjust-wallet', [StoreCustomerWalletController::class, 'adjustWallet'])->name('store.wallets.adjust-wallet');
            Route::post('/{wallet}/adjust-loyalty', [StoreCustomerWalletController::class, 'adjustLoyalty'])->name('store.wallets.adjust-loyalty');
        });

        // Operational KPI Dashboard (P27)
        Route::get('/kpis', [StoreKpiDashboardController::class, 'index'])->name('store.kpis.index');

        // Advanced Reports & Analytics (P33)
        Route::prefix('reports')->group(function () {
            Route::get('/', [StoreReportController::class, 'index'])->name('store.reports.index');
            Route::get('/export/{type}', [StoreReportController::class, 'export'])->name('store.reports.export');
        });

        // Suppliers & Batches Gateway (P27)
        Route::prefix('suppliers')->group(function () {
            Route::get('/', [StoreSupplierController::class, 'index'])->name('store.suppliers.index');
            Route::post('/', [StoreSupplierController::class, 'store'])->name('store.suppliers.store');
            Route::get('/{supplier}', [StoreSupplierController::class, 'show'])->name('store.suppliers.show');
            Route::post('/{supplier}/batches', [StoreSupplierController::class, 'storeBatch'])->name('store.suppliers.batches.store');
        });

        // Custom Domains Management (P28)
        Route::prefix('domains')->group(function () {
            Route::get('/', [StoreDomainController::class, 'index'])->name('store.domains.index');
            Route::post('/', [StoreDomainController::class, 'store'])->name('store.domains.store');
            Route::post('/toggle-redirect', [StoreDomainController::class, 'toggleRedirect'])->name('store.domains.toggle-redirect');
            Route::post('/{domain}/verify', [StoreDomainController::class, 'verify'])->name('store.domains.verify');
            Route::post('/{domain}/primary', [StoreDomainController::class, 'setPrimary'])->name('store.domains.set-primary');
            Route::delete('/{domain}', [StoreDomainController::class, 'destroy'])->name('store.domains.destroy');
        });

        // Theme Customizer (P28)
        Route::prefix('theme')->group(function () {
            Route::get('/', [StoreThemeController::class, 'edit'])->name('store.theme.edit');
            Route::post('/', [StoreThemeController::class, 'update'])->name('store.theme.update');
        });

        // Ad Tracking & Conversions API (P30)
        Route::prefix('pixels')->group(function () {
            Route::get('/', [StorePixelTrackingController::class, 'index'])->name('store.pixels.index');
            Route::post('/', [StorePixelTrackingController::class, 'update'])->name('store.pixels.update');
            Route::post('/test', [StorePixelTrackingController::class, 'test'])->name('store.pixels.test');
        });

        // AI Tools & Assistant (P31)
        Route::prefix('ai')->group(function () {
            Route::get('/', [StoreAiToolsController::class, 'index'])->name('store.ai.index');
            Route::post('/generate-product-copy', [StoreAiToolsController::class, 'generateProductCopy'])->name('store.ai.product-copy');
            Route::post('/suggest-tags', [StoreAiToolsController::class, 'suggestTags'])->name('store.ai.suggest-tags');
            Route::post('/translate', [StoreAiToolsController::class, 'translate'])->name('store.ai.translate');
        });

        // Support Tickets & Helpdesk (P32)
        Route::prefix('support')->group(function () {
            Route::get('/', [StoreSupportTicketController::class, 'index'])->name('store.support.index');
            Route::post('/platform-ticket', [StoreSupportTicketController::class, 'createPlatformTicket'])->name('store.support.platform-ticket');
            Route::get('/{id}', [StoreSupportTicketController::class, 'show'])->name('store.support.show');
            Route::post('/{id}/reply', [StoreSupportTicketController::class, 'reply'])->name('store.support.reply');
            Route::post('/{id}/status', [StoreSupportTicketController::class, 'updateStatus'])->name('store.support.status');
            Route::post('/{id}/assign', [StoreSupportTicketController::class, 'assign'])->name('store.support.assign');
        });
    });

    // Courier Webhook endpoint for tracking callbacks (P24)
    Route::post('/webhooks/courier/{driver}', [CourierWebhookController::class, 'handle'])->name('store.courier.webhook');

    // Affiliate Portal Routes (P16)
    Route::prefix('partners')->group(function () {
        Route::get('/join', [AffiliateAuthController::class, 'showJoin'])->name('affiliate.join');
        Route::post('/register', [AffiliateAuthController::class, 'register'])->middleware('throttle:10,1')->name('affiliate.register');
        Route::get('/login', [AffiliateAuthController::class, 'showLogin'])->name('affiliate.login');
        Route::post('/login', [AffiliateAuthController::class, 'login'])->middleware('throttle:10,1')->name('affiliate.login.submit');
        Route::get('/verify-otp', [AffiliateAuthController::class, 'showVerifyOtp'])->name('affiliate.verify-otp.show');
        Route::post('/verify-otp', [AffiliateAuthController::class, 'verifyOtp'])->middleware('throttle:10,1')->name('affiliate.verify-otp.submit');

        Route::middleware(EnsureAffiliateAuthenticated::class)->group(function () {
            Route::post('/logout', [AffiliateAuthController::class, 'logout'])->name('affiliate.logout');
            Route::get('/dashboard', [AffiliateDashboardController::class, 'index'])->name('affiliate.dashboard');
            Route::get('/kyc', [AffiliateKycController::class, 'show'])->name('affiliate.kyc.show');
            Route::post('/kyc', [AffiliateKycController::class, 'submit'])->name('affiliate.kyc.submit');
            Route::get('/payout-settings', [AffiliatePayoutController::class, 'show'])->name('affiliate.payout-settings.show');
            Route::post('/payout-settings/request-otp', [AffiliatePayoutController::class, 'requestOtp'])->middleware('throttle:3,1')->name('affiliate.payout-settings.request-otp');
            Route::post('/payout-settings', [AffiliatePayoutController::class, 'update'])->middleware('throttle:10,1')->name('affiliate.payout-settings.update');

            // Affiliate Ad Tracking Pixels (P30)
            Route::get('/pixels', [AffiliatePixelController::class, 'show'])->name('affiliate.pixels.show');
            Route::post('/pixels', [AffiliatePixelController::class, 'update'])->name('affiliate.pixels.update');

            // Affiliate AI Copywriter (P31)
            Route::prefix('ai')->group(function () {
                Route::get('/', [AffiliateAiToolsController::class, 'index'])->name('affiliate.ai.index');
                Route::post('/generate-ad-copy', [AffiliateAiToolsController::class, 'generateAdCopy'])->name('affiliate.ai.ad-copy');
                Route::post('/suggest-angles', [AffiliateAiToolsController::class, 'suggestAngles'])->name('affiliate.ai.suggest-angles');
                Route::post('/check-policy', [AffiliateAiToolsController::class, 'checkPolicy'])->name('affiliate.ai.check-policy');
            });

            // Affiliate Support Tickets (P32)
            Route::prefix('support')->group(function () {
                Route::get('/', [AffiliateSupportController::class, 'index'])->name('affiliate.support.index');
                Route::get('/create', [AffiliateSupportController::class, 'create'])->name('affiliate.support.create');
                Route::post('/', [AffiliateSupportController::class, 'store'])->name('affiliate.support.store');
                Route::get('/{id}', [AffiliateSupportController::class, 'show'])->name('affiliate.support.show');
                Route::post('/{id}/reply', [AffiliateSupportController::class, 'reply'])->name('affiliate.support.reply');
            });

            // Affiliate Help Center & FAQ (P32)
            Route::get('/help', [AffiliateHelpCenterController::class, 'index'])->name('affiliate.help.index');

            // Affiliate Wallet & Statement (P19)
            Route::get('/wallet', [AffiliateWalletController::class, 'index'])->name('affiliate.wallet.index');
            Route::get('/wallet/export', [AffiliateWalletController::class, 'exportCsv'])->name('affiliate.wallet.export');

            // Affiliate Payout Requests (P20)
            Route::get('/payouts', [AffiliatePayoutRequestController::class, 'index'])->name('affiliate.payouts.index');
            Route::post('/payouts', [AffiliatePayoutRequestController::class, 'store'])->name('affiliate.payouts.store');
            Route::post('/payouts/{id}/cancel', [AffiliatePayoutRequestController::class, 'cancel'])->name('affiliate.payouts.cancel');

            // Affiliate Tracking Links (P18)
            Route::get('/links', [AffiliateTrackingLinkController::class, 'index'])->name('affiliate.links.index');
            Route::get('/links/create', [AffiliateTrackingLinkController::class, 'create'])->name('affiliate.links.create');
            Route::post('/links', [AffiliateTrackingLinkController::class, 'store'])->name('affiliate.links.store');
            Route::delete('/links/{id}', [AffiliateTrackingLinkController::class, 'destroy'])->name('affiliate.links.destroy');

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

            // Affiliate PWA App (P21 - Inertia + React + TypeScript)
            Route::prefix('app')->group(function () {
                Route::get('/', AppDashboardController::class)->name('affiliate.app.dashboard');

                // Catalog
                Route::get('/catalog', [AppCatalogController::class, 'index'])->name('affiliate.app.catalog.index');
                Route::get('/catalog/{product}', [AppCatalogController::class, 'show'])->name('affiliate.app.catalog.show');

                // Orders
                Route::get('/orders', [AppOrderController::class, 'index'])->name('affiliate.app.orders.index');
                Route::get('/orders/create', [AppOrderController::class, 'create'])->name('affiliate.app.orders.create');
                Route::post('/orders/quote', [AppOrderController::class, 'quote'])->name('affiliate.app.orders.quote');
                Route::post('/orders', [AppOrderController::class, 'store'])->name('affiliate.app.orders.store');
                Route::get('/orders/{reference}', [AppOrderController::class, 'show'])->name('affiliate.app.orders.show');
                Route::post('/orders/{reference}/cancel', [AppOrderController::class, 'cancel'])->name('affiliate.app.orders.cancel');

                // Wallet & Payout
                Route::get('/wallet', [AppWalletController::class, 'index'])->name('affiliate.app.wallet.index');
                Route::get('/wallet/main', [AppWalletController::class, 'index'])->name('affiliate.app.wallet');
                Route::post('/wallet/payout', [AppWalletController::class, 'requestPayout'])->name('affiliate.app.wallet.payout');

                // Tracking Links
                Route::get('/links', [AppLinksController::class, 'index'])->name('affiliate.app.links.index');
                Route::post('/links', [AppLinksController::class, 'store'])->name('affiliate.app.links.store');
                Route::delete('/links/{id}', [AppLinksController::class, 'destroy'])->name('affiliate.app.links.destroy');

                // Analytics
                Route::get('/analytics', [AppAnalyticsController::class, 'index'])->name('affiliate.app.analytics.index');

                // Levels, Quality & Referrals (P22)
                Route::get('/levels', [AppLevelsController::class, 'index'])->name('affiliate.app.levels.index');

                // Profile & KYC & Payout Settings
                Route::get('/profile', [AppProfileController::class, 'index'])->name('affiliate.app.profile.index');
                Route::post('/profile', [AppProfileController::class, 'update'])->name('affiliate.app.profile.update');
                Route::post('/profile/kyc', [AppProfileController::class, 'submitKyc'])->name('affiliate.app.profile.kyc');
                Route::post('/profile/payout-otp', [AppProfileController::class, 'requestPayoutOtp'])->middleware('throttle:3,1')->name('affiliate.app.profile.payout-otp');
                Route::post('/profile/payout-settings', [AppProfileController::class, 'updatePayoutSettings'])->middleware('throttle:10,1')->name('affiliate.app.profile.payout-settings');
            });
        });
    });

    // Health check endpoint from P06
    Route::get('/storefront', function () {
        return 'Storefront Active';
    });
});
