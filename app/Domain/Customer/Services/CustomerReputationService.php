<?php

declare(strict_types=1);

namespace App\Domain\Customer\Services;

use App\Models\CustomerReputation;

class CustomerReputationService
{
    /**
     * Record a successfully delivered order for this phone hash.
     */
    public function recordDelivered(string $phoneHash, ?string $tenantId = null): CustomerReputation
    {
        /** @var CustomerReputation $reputation */
        $reputation = CustomerReputation::query()->firstOrCreate(
            [
                'phone_hash' => $phoneHash,
                'tenant_id' => $tenantId,
            ],
            [
                'delivered_orders_count' => 0,
                'refused_orders_count' => 0,
                'total_orders_count' => 0,
            ]
        );

        $reputation->increment('delivered_orders_count');
        $reputation->increment('total_orders_count');
        $reputation->update(['last_seen_at' => now()]);

        return $reputation->fresh() ?? $reputation;
    }

    /**
     * Record a refused / rejected delivery for this phone hash.
     */
    public function recordRefused(string $phoneHash, ?string $tenantId = null): CustomerReputation
    {
        /** @var CustomerReputation $reputation */
        $reputation = CustomerReputation::query()->firstOrCreate(
            [
                'phone_hash' => $phoneHash,
                'tenant_id' => $tenantId,
            ],
            [
                'delivered_orders_count' => 0,
                'refused_orders_count' => 0,
                'total_orders_count' => 0,
            ]
        );

        $reputation->increment('refused_orders_count');
        $reputation->increment('total_orders_count');
        $reputation->update(['last_seen_at' => now()]);

        return $reputation->fresh() ?? $reputation;
    }

    /**
     * Retrieve the reputation record.
     */
    public function getReputation(string $phoneHash, ?string $tenantId = null): ?CustomerReputation
    {
        return CustomerReputation::query()
            ->where('phone_hash', $phoneHash)
            ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
            ->first();
    }

    /**
     * Calculate reputation delivery score (0 to 100).
     */
    public function getDeliveryScore(string $phoneHash, ?string $tenantId = null): float
    {
        $rep = $this->getReputation($phoneHash, $tenantId);
        if ($rep === null) {
            return 100.0; // New customer has clean baseline
        }

        return $rep->getDeliveryRate();
    }
}
