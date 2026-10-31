<?php

declare(strict_types=1);

namespace App\Support\Settings;

class GeneralSettings extends Settings
{
    public string $platform_name = 'AffiliateOS';

    /** ISO 3166-1 alpha-2 home country of the store (config/countries.php). */
    public string $country_code = 'EG';

    public string $default_currency = 'EGP';

    /** @var list<string> extra countries the store ships to, besides its home country */
    public array $shipping_countries = [];

    public string $default_locale = 'ar';

    public ?string $logo_url = null;

    public string $primary_color = '#6366f1';

    public string $secondary_color = '#a855f7';

    public string $support_phone = '+201000000000';

    public ?string $support_whatsapp = null;

    public string $support_email = 'support@affiliateos.test';

    public static function group(): string
    {
        return 'general';
    }
}
