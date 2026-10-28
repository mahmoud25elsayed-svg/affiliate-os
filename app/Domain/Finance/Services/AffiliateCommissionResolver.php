<?php

declare(strict_types=1);

namespace App\Domain\Finance\Services;

use Lunar\Models\Order;

class AffiliateCommissionResolver
{
    /**
     * Resolve the affiliate commission in minor units for the given order.
     */
    public static function getCommissionMinor(Order $order): int
    {
        /** @var mixed $rawSnapshot */
        $rawSnapshot = $order->getAttribute('price_snapshot');
        if (is_string($rawSnapshot)) {
            $snapshot = json_decode($rawSnapshot, true);
            if (is_array($snapshot) && isset($snapshot['affiliate_profit_minor'])) {
                return (int) $snapshot['affiliate_profit_minor'];
            }
        } elseif (is_array($rawSnapshot) && isset($rawSnapshot['affiliate_profit_minor'])) {
            return (int) $rawSnapshot['affiliate_profit_minor'];
        }

        /** @var mixed $meta */
        $meta = $order->getAttribute('meta');
        if (is_array($meta) && isset($meta['affiliate_commission_minor'])) {
            return (int) $meta['affiliate_commission_minor'];
        }

        return 0;
    }
}
