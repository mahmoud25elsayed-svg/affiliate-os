<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Finance\Services\CommissionSettlementService;
use Illuminate\Console\Command;

class SettleAffiliateCommissionsCommand extends Command
{
    protected $signature = 'affiliate:settle-commissions {--days= : Override the return window days threshold}';

    protected $description = 'Settle delivered affiliate orders whose return window has elapsed and release earnings to available balance';

    public function handle(CommissionSettlementService $settlementService): int
    {
        $days = $this->option('days') !== null ? (int) $this->option('days') : null;

        $this->info('جاري فحص وتسوية الطلبات المسلمة المؤهلة...');

        $settled = $settlementService->settleEligibleDeliveredOrders($days);

        $this->info("تمت تسوية {$settled} طلباً بنجاح ونقل أرباحها إلى الأرصدة المتاحة للمسوقين.");

        return self::SUCCESS;
    }
}
