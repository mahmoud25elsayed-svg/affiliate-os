<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Finance\Gateways\ManualPayoutGateway;
use App\Domain\Finance\Gateways\PayoutGatewayInterface;
use App\Domain\Finance\Listeners\OrderDeliveredLedgerListener;
use App\Domain\Finance\Listeners\OrderReturnedLedgerListener;
use App\Domain\Finance\Services\AccountResolver;
use App\Domain\Finance\Services\CommissionSettlementService;
use App\Domain\Finance\Services\Ledger;
use App\Domain\Finance\Services\PayoutBatchService;
use App\Domain\Finance\Services\PayoutService;
use App\Domain\Finance\Services\PayoutTurnaroundCalculator;
use App\Domain\Orders\Events\OrderDelivered;
use App\Domain\Orders\Events\OrderReturned;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class FinanceServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(Ledger::class);
        $this->app->singleton(AccountResolver::class);
        $this->app->singleton(CommissionSettlementService::class);
        $this->app->singleton(PayoutService::class);
        $this->app->singleton(PayoutBatchService::class);
        $this->app->singleton(PayoutTurnaroundCalculator::class);
        $this->app->bind(PayoutGatewayInterface::class, ManualPayoutGateway::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Event::listen(OrderDelivered::class, OrderDeliveredLedgerListener::class);
        Event::listen(OrderReturned::class, OrderReturnedLedgerListener::class);
    }
}
