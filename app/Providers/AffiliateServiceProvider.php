<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Affiliate\Services\AffiliateKycService;
use App\Domain\Affiliate\Services\AffiliatePayoutAccountService;
use App\Domain\Affiliate\Services\AffiliateRegistrationService;
use Illuminate\Support\ServiceProvider;

class AffiliateServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(AffiliateRegistrationService::class);
        $this->app->singleton(AffiliateKycService::class);
        $this->app->singleton(AffiliatePayoutAccountService::class);
    }

    public function boot(): void
    {
        //
    }
}
