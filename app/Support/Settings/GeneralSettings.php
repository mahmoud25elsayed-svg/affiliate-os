<?php

declare(strict_types=1);

namespace App\Support\Settings;

class GeneralSettings extends Settings
{
    public string $platform_name = 'AffiliateOS';

    public string $default_currency = 'EGP';

    public string $default_locale = 'ar';

    public string $support_phone = '+201000000000';

    public string $support_email = 'support@affiliateos.test';

    public static function group(): string
    {
        return 'general';
    }
}
