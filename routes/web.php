<?php

use App\Http\Controllers\Auth\TwoFactorController;
use App\Http\Controllers\Billing\InvoiceDownloadController;
use App\Http\Controllers\HealthCheckController;
use App\Http\Controllers\MerchantOnboardingController;
use App\Http\Controllers\Platform\PlatformAnalyticsController;
use App\Http\Controllers\Platform\PlatformMarketingController;
use App\Http\Middleware\TrackPlatformVisitorsMiddleware;
use App\Support\Auth\ImpersonationService;
use Illuminate\Support\Facades\Route;

// System Health Monitoring Endpoint (P36)
Route::get('/health', HealthCheckController::class)->name('health.check');

// Public Marketing Website & Merchant Onboarding (Scoped to Central Domains)
$centralDomains = (array) config('tenancy.central_domains', ['localhost', '127.0.0.1']);
foreach ($centralDomains as $index => $centralDomain) {
    Route::domain($centralDomain)->middleware([TrackPlatformVisitorsMiddleware::class])->group(function () use ($index) {
        $homeRoute = Route::get('/', [PlatformMarketingController::class, 'home']);
        if ($index === 0) {
            $homeRoute->name('platform.marketing.home');
            Route::get('/home', fn () => redirect()->route('platform.marketing.home'))->name('landing');
            Route::get('/features', [PlatformMarketingController::class, 'features'])->name('platform.marketing.features');
            Route::get('/pricing', [PlatformMarketingController::class, 'pricing'])->name('platform.marketing.pricing');
            Route::get('/faq', [PlatformMarketingController::class, 'faq'])->name('platform.marketing.faq');
            Route::get('/blog', [PlatformMarketingController::class, 'blog'])->name('platform.marketing.blog');
            Route::get('/blog/{slug}', [PlatformMarketingController::class, 'blogPost'])->name('platform.marketing.blog.post');
            Route::get('/contact', [PlatformMarketingController::class, 'contact'])->name('platform.marketing.contact');
            Route::post('/contact', [PlatformMarketingController::class, 'submitContact'])->name('platform.marketing.contact.submit');

            // Legal Documents (O-7)
            Route::get('/terms', fn () => app(PlatformMarketingController::class)->legal('terms'))->name('legal.terms');
            Route::get('/privacy', fn () => app(PlatformMarketingController::class)->legal('privacy'))->name('legal.privacy');
            Route::get('/dpa', fn () => app(PlatformMarketingController::class)->legal('dpa'))->name('legal.dpa');
            Route::get('/affiliate-terms', fn () => app(PlatformMarketingController::class)->legal('affiliate-terms'))->name('legal.affiliate-terms');

            // Favicon & XML Sitemap (SM-09)
            Route::get('/favicon.ico', fn () => file_exists(public_path('favicon.ico')) ? response()->file(public_path('favicon.ico'), ['Content-Type' => 'image/x-icon']) : response('', 204))->name('platform.favicon');
            Route::get('/sitemap.xml', [PlatformMarketingController::class, 'sitemap'])->name('platform.sitemap');

            // Authentication Shortcuts (O-2)
            Route::get('/login', fn () => redirect('/platform/login'))->name('login');
        } else {
            Route::get('/home', fn () => redirect()->route('platform.marketing.home'));
            Route::get('/features', [PlatformMarketingController::class, 'features']);
            Route::get('/pricing', [PlatformMarketingController::class, 'pricing']);
            Route::get('/faq', [PlatformMarketingController::class, 'faq']);
            Route::get('/blog', [PlatformMarketingController::class, 'blog']);
            Route::get('/blog/{slug}', [PlatformMarketingController::class, 'blogPost']);
            Route::get('/contact', [PlatformMarketingController::class, 'contact']);
            Route::post('/contact', [PlatformMarketingController::class, 'submitContact']);
            Route::get('/terms', fn () => app(PlatformMarketingController::class)->legal('terms'));
            Route::get('/privacy', fn () => app(PlatformMarketingController::class)->legal('privacy'));
            Route::get('/dpa', fn () => app(PlatformMarketingController::class)->legal('dpa'));
            Route::get('/affiliate-terms', fn () => app(PlatformMarketingController::class)->legal('affiliate-terms'));
            Route::get('/favicon.ico', fn () => file_exists(public_path('favicon.ico')) ? response()->file(public_path('favicon.ico'), ['Content-Type' => 'image/x-icon']) : response('', 204));
            Route::get('/sitemap.xml', [PlatformMarketingController::class, 'sitemap']);
            Route::get('/login', fn () => redirect('/platform/login'));
        }
    });
}

Route::get('/register/merchant', [MerchantOnboardingController::class, 'showRegister'])->name('onboarding.register');
Route::get('/onboarding/check-subdomain', [MerchantOnboardingController::class, 'checkSubdomain'])
    ->middleware('throttle:30,1')
    ->name('onboarding.check-subdomain');
Route::post('/onboarding/send-otp', [MerchantOnboardingController::class, 'sendOtp'])
    ->middleware('throttle:10,1')
    ->name('onboarding.send-otp');
Route::post('/register/merchant', [MerchantOnboardingController::class, 'register'])
    ->middleware('throttle:10,1')
    ->name('onboarding.register.submit');
Route::get('/onboarding/status/{tenant}', [MerchantOnboardingController::class, 'provisioningStatus'])
    ->middleware('throttle:30,1')
    ->name('onboarding.provisioning');

// Stop Impersonation
Route::post('/impersonation/stop', function (ImpersonationService $impersonationService) {
    $impersonationService->stopImpersonating();

    return redirect('/platform');
})->name('impersonation.stop');

// Platform Admin 2FA
Route::middleware(['auth'])->group(function () {
    Route::get('/platform/2fa/setup', [TwoFactorController::class, 'showSetup'])->name('platform.2fa.setup');
    Route::post('/platform/2fa/confirm', [TwoFactorController::class, 'confirmSetup'])->name('platform.2fa.confirm');
    Route::get('/platform/2fa/challenge', [TwoFactorController::class, 'showChallenge'])->name('platform.2fa.challenge');
    Route::post('/platform/2fa/verify', [TwoFactorController::class, 'verifyChallenge'])->name('platform.2fa.verify');

    // Invoice PDF Download
    Route::get('/billing/invoices/{invoice}/download', InvoiceDownloadController::class)->name('billing.invoices.download');

    // Central Platform Analytics (P33)
    Route::get('/platform/analytics', [PlatformAnalyticsController::class, 'index'])->name('platform.analytics.index');
});
