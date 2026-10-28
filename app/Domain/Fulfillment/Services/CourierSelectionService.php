<?php

declare(strict_types=1);

namespace App\Domain\Fulfillment\Services;

use App\Models\CourierAccount;
use App\Models\CourierRule;
use App\Support\Location\Governorate;
use Lunar\DataTypes\Price;
use Lunar\Models\Order;
use Lunar\Models\OrderAddress;

class CourierSelectionService
{
    /**
     * Determine the optimal CourierAccount for an order based on rules and routing policy.
     */
    public function selectCourier(Order $order): ?CourierAccount
    {
        $tenantId = $order->tenant_id ?? (tenant('id') ? (string) tenant('id') : null);

        // 1. Resolve governorate ID
        $governorateId = $this->resolveGovernorateId($order);
        /** @var mixed $rawTotal */
        $rawTotal = $order->total;
        $orderTotalMinor = (int) ($rawTotal instanceof Price ? $rawTotal->value : $rawTotal);

        // 2. Query matching active rules sorted by priority DESC
        if ($governorateId !== null) {
            /** @var CourierRule|null $matchedRule */
            $matchedRule = CourierRule::query()
                ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
                ->where('is_active', true)
                ->where('governorate_id', $governorateId)
                ->where(function ($q) use ($orderTotalMinor) {
                    $q->whereNull('cod_limit_minor')
                        ->orWhere('cod_limit_minor', '>=', $orderTotalMinor);
                })
                ->orderByDesc('priority')
                ->with('courierAccount')
                ->first();

            if ($matchedRule && $matchedRule->courierAccount && $matchedRule->courierAccount->is_active) {
                return $matchedRule->courierAccount;
            }
        }

        // 3. Fallback to default active courier account
        /** @var CourierAccount|null $defaultAccount */
        $defaultAccount = CourierAccount::query()
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->where('is_active', true)
            ->where('is_default', true)
            ->first();

        if ($defaultAccount instanceof CourierAccount) {
            return $defaultAccount;
        }

        // 4. Fallback to any active courier account
        return CourierAccount::query()
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->where('is_active', true)
            ->first();
    }

    protected function resolveGovernorateId(Order $order): ?int
    {
        // Try price snapshot first
        $snapshot = is_array($order->price_snapshot)
            ? $order->price_snapshot
            : json_decode((string) $order->price_snapshot, true) ?? [];

        if (! empty($snapshot['governorate_id'])) {
            return (int) $snapshot['governorate_id'];
        }

        // Try address
        /** @var OrderAddress|null $address */
        $address = $order->shippingAddress()->first();
        if ($address && ! empty($address->city)) {
            $govId = Governorate::query()
                ->where('name_ar', 'like', "%{$address->city}%")
                ->orWhere('name_en', 'like', "%{$address->city}%")
                ->value('id');

            if ($govId !== null) {
                return (int) $govId;
            }
        }

        return null;
    }
}
