<?php

declare(strict_types=1);

namespace App\Support\Settings;

class ShippingSettings extends Settings
{
    public int $default_rate_minor = 5000; // 50 EGP in piasters

    public int $free_shipping_threshold_minor = 100000; // 1,000 EGP in piasters (0 = disabled)

    public int $cod_fee_minor = 1500; // 15 EGP in piasters

    public bool $allow_cod = true;

    public string $default_delivery_time_ar = 'خلال 2-4 أيام عمل';

    public static function group(): string
    {
        return 'shipping';
    }
}
