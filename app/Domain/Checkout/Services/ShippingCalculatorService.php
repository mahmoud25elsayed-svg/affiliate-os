<?php

declare(strict_types=1);

namespace App\Domain\Checkout\Services;

use App\Domain\Checkout\DTOs\ShippingQuote;
use App\Models\Coupon;
use App\Models\TenantShippingRate;
use App\Support\Settings\ShippingSettings;

class ShippingCalculatorService
{
    public function calculate(int $governorateId, int $subtotalMinor, ?Coupon $coupon = null): ShippingQuote
    {
        $settings = ShippingSettings::load();

        // Check for governorate specific override
        /** @var TenantShippingRate|null $specificRate */
        $specificRate = TenantShippingRate::query()
            ->where('governorate_id', $governorateId)
            ->where('is_active', true)
            ->first();

        $baseRate = $specificRate ? $specificRate->rate_minor : $settings->default_rate_minor;
        $deliveryTime = $specificRate ? $specificRate->delivery_time_ar : $settings->default_delivery_time_ar;

        // Check for Free Shipping Coupon
        if ($coupon !== null && $coupon->type === Coupon::TYPE_FREE_SHIPPING) {
            return new ShippingQuote(
                rate_minor: 0,
                delivery_time_ar: $deliveryTime,
                is_free: true,
                free_reason: 'coupon'
            );
        }

        // Check for Free Shipping Order Threshold
        if ($settings->free_shipping_threshold_minor > 0 && $subtotalMinor >= $settings->free_shipping_threshold_minor) {
            return new ShippingQuote(
                rate_minor: 0,
                delivery_time_ar: $deliveryTime,
                is_free: true,
                free_reason: 'threshold'
            );
        }

        return new ShippingQuote(
            rate_minor: $baseRate,
            delivery_time_ar: $deliveryTime,
            is_free: false,
            free_reason: null
        );
    }
}
