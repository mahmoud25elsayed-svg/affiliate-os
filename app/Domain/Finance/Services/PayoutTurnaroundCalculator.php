<?php

declare(strict_types=1);

namespace App\Domain\Finance\Services;

use App\Models\PayoutRequest;

class PayoutTurnaroundCalculator
{
    /**
     * Calculate average turnaround time in seconds from creation to payment.
     * Compatible with all database engines (SQLite, MySQL, PostgreSQL).
     */
    public function getAverageTurnaroundSeconds(): ?int
    {
        $tenantId = tenant('id') ? (string) tenant('id') : null;

        $query = PayoutRequest::query()
            ->where('status', PayoutRequest::STATUS_PAID)
            ->whereNotNull('paid_at');

        if ($tenantId !== null) {
            $query->where('tenant_id', $tenantId);
        }

        $records = $query->latest('paid_at')
            ->take(100)
            ->get(['created_at', 'paid_at']);

        if ($records->isEmpty()) {
            return null;
        }

        $totalSeconds = $records->sum(function (PayoutRequest $req) {
            return $req->paid_at
                ? abs($req->paid_at->diffInSeconds($req->created_at))
                : 0;
        });

        return (int) round($totalSeconds / $records->count());
    }

    /**
     * Get a human-readable Arabic representation of the average payout speed.
     */
    public function getAverageTurnaroundHuman(): string
    {
        $seconds = $this->getAverageTurnaroundSeconds();

        if ($seconds === null || $seconds <= 0) {
            return 'خلال 24 إلى 48 ساعة';
        }

        $hours = $seconds / 3600;

        if ($hours < 1) {
            return 'أقل من ساعة واحدة';
        }

        if ($hours < 24) {
            $roundedHours = (int) round($hours);

            return "حوالي {$roundedHours} ساعة";
        }

        $days = round($hours / 24, 1);

        return "حوالي {$days} يوم";
    }
}
