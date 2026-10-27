<?php

declare(strict_types=1);

namespace App\Support\Settings;

class GeneralSettings extends Settings
{
    public string $platform_name = 'AffiliateOS';

    public string $default_currency = 'EGP';

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
