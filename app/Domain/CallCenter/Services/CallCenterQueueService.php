<?php

declare(strict_types=1);

namespace App\Domain\CallCenter\Services;

use App\Domain\CallCenter\Settings\CallCenterSettings;
use App\Domain\Orders\Enums\OrderStatus;
use App\Models\CallAttempt;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Lunar\Models\Order;

class CallCenterQueueService
{
    /**
     * Atomically claim the next eligible order from the confirmation queue for an agent.
     */
    public function claimNextOrder(User $agent): ?Order
    {
        $tenantId = $agent->tenant_id;
        $settings = CallCenterSettings::load();
        $lockMinutes = $settings->getLockDurationMinutes();
        $now = Carbon::now();

        // 1. Check if the agent already holds an active unexpired lock on a pending order
        /** @var Order|null $existingLocked */
        $existingLocked = Order::query()
            ->where('tenant_id', $tenantId)
            ->where('status', OrderStatus::PENDING_CONFIRMATION->value)
            ->where('assigned_to', $agent->id)
            ->where('locked_until', '>', $now)
            ->with(['lines.purchasable', 'addresses'])
            ->first();

        if ($existingLocked instanceof Order) {
            return $existingLocked;
        }

        // 2. Atomically select and lock the next candidate
        return DB::transaction(function () use ($tenantId, $agent, $lockMinutes, $now) {
            /** @var Order|null $candidate */
            $candidate = Order::query()
                ->where('tenant_id', $tenantId)
                ->where('status', OrderStatus::PENDING_CONFIRMATION->value)
                ->where(function ($query) use ($now) {
                    $query->whereNull('locked_until')
                        ->orWhere('locked_until', '<=', $now);
                })
                ->where(function ($query) use ($now) {
                    $query->whereNull('next_attempt_at')
                        ->orWhere('next_attempt_at', '<=', $now);
                })
                ->orderByDesc('confirmation_priority')
                ->orderBy('next_attempt_at')
                ->orderBy('id')
                ->lockForUpdate()
                ->first();

            if (! $candidate instanceof Order) {
                return null;
            }

            // Lock this order for the agent
            $candidate->update([
                'assigned_to' => $agent->id,
                'locked_until' => Carbon::now()->addMinutes($lockMinutes),
            ]);

            return $candidate->load(['lines.purchasable', 'addresses']);
        });
    }

    /**
     * Release an agent's lock on an order back to the queue.
     */
    public function releaseOrder(Order $order, User $agent): void
    {
        if ((int) $order->assigned_to === $agent->id) {
            $order->update([
                'assigned_to' => null,
                'locked_until' => null,
            ]);
        }
    }

    /**
     * Get aggregate statistics for the confirmation queue.
     *
     * @return array<string, int>
     */
    public function getQueueStats(?string $tenantId = null): array
    {
        $tenantId = $tenantId ?? (tenant('id') ? (string) tenant('id') : null);
        $now = Carbon::now();
        $today = Carbon::today();

        $pendingCount = Order::query()
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->where('status', OrderStatus::PENDING_CONFIRMATION->value)
            ->count();

        $lockedCount = Order::query()
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->where('status', OrderStatus::PENDING_CONFIRMATION->value)
            ->where('locked_until', '>', $now)
            ->count();

        $dueCount = Order::query()
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->where('status', OrderStatus::PENDING_CONFIRMATION->value)
            ->where(function ($q) use ($now) {
                $q->whereNull('next_attempt_at')
                    ->orWhere('next_attempt_at', '<=', $now);
            })
            ->count();

        $todayConfirmed = CallAttempt::query()
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->where('outcome', 'confirmed')
            ->where('created_at', '>=', $today)
            ->count();

        $todayCancelled = CallAttempt::query()
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->whereIn('outcome', ['customer_cancelled', 'wrong_number'])
            ->where('created_at', '>=', $today)
            ->count();

        $todayAttempts = CallAttempt::query()
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->where('created_at', '>=', $today)
            ->count();

        return [
            'pending_count' => $pendingCount,
            'locked_count' => $lockedCount,
            'due_count' => $dueCount,
            'today_confirmed' => $todayConfirmed,
            'today_cancelled' => $todayCancelled,
            'today_attempts' => $todayAttempts,
        ];
    }
}
