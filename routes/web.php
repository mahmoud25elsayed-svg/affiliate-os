<?php

use App\Http\Controllers\Auth\TwoFactorController;
use App\Http\Controllers\Billing\InvoiceDownloadController;
use App\Http\Controllers\MerchantOnboardingController;
use App\Http\Controllers\Platform\PlatformAnalyticsController;
use App\Support\Auth\ImpersonationService;
use Illuminate\Support\Facades\Route;

// Public Landing & Merchant Onboarding (Scoped to Central Domains)
$centralDomains = (array) config('tenancy.central_domains', ['localhost', '127.0.0.1']);
foreach ($centralDomains as $index => $centralDomain) {
    $route = Route::domain($centralDomain)->get('/', [MerchantOnboardingController::class, 'showLanding']);
    if ($index === 0) {
        $route->name('landing');
    }
}
Route::get('/register/merchant', [MerchantOnboardingController::class, 'showRegister'])->name('onboarding.register');
Route::get('/onboarding/check-subdomain', [MerchantOnboardingController::class, 'checkSubdomain'])->name('onboarding.check-subdomain');
Route::post('/onboarding/send-otp', [MerchantOnboardingController::class, 'sendOtp'])->name('onboarding.send-otp');
Route::post('/register/merchant', [MerchantOnboardingController::class, 'register'])->name('onboarding.register.submit');
Route::get('/onboarding/status/{tenant}', [MerchantOnboardingController::class, 'provisioningStatus'])->name('onboarding.provisioning');

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
