<?php

declare(strict_types=1);

namespace App\Support\Location;

use App\Support\Settings\GeneralSettings;

/**
 * Where the current store sells: its home country, its currency and the countries it ships to.
 * Outside a tenant (platform pages, jobs without tenancy) it falls back to the platform defaults.
 */
final class Market
{
    public const DEFAULT_COUNTRY = 'EG';

    public static function country(): Country
    {
        return Country::find(GeneralSettings::load()->country_code) ?? Country::get(self::DEFAULT_COUNTRY);
    }

    public static function currency(): string
    {
        $currency = strtoupper(GeneralSettings::load()->default_currency);

        return is_array(config("currencies.{$currency}")) ? $currency : self::country()->currency;
    }

    /**
     * Countries the store delivers to (always includes its home country).
     *
     * @return list<string>
     */
    public static function shippingCountries(): array
    {
        $home = self::country()->code;
        $configured = array_filter(
            GeneralSettings::load()->shipping_countries,
            fn (string $code): bool => Country::find($code) !== null
        );

        return array_values(array_unique([$home, ...$configured]));
    }
}
