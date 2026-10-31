<?php

declare(strict_types=1);

namespace App\Filament\Store\Pages;

use App\Support\Location\Country;
use App\Support\Settings\GeneralSettings;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Validation\Rule;
use Lunar\Models\Currency;

class ManageStoreSettings extends Page
{
    protected string $view = 'filament.store.pages.manage-store-settings';

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $navigationLabel = 'إعدادات المتجر';

    protected static ?string $title = 'إعدادات المتجر';

    public ?string $platform_name = '';

    public ?string $logo_url = '';

    public string $primary_color = '#6366f1';

    public string $secondary_color = '#a855f7';

    public string $default_locale = 'ar';

    public string $default_currency = 'EGP';

    public string $country_code = 'EG';

    /** @var list<string> */
    public array $shipping_countries = [];

    public ?string $support_phone = '';

    public ?string $support_whatsapp = '';

    public ?string $support_email = '';

    public function mount(): void
    {
        $settings = GeneralSettings::load();

        $this->platform_name = $settings->platform_name;
        $this->logo_url = $settings->logo_url ?? '';
        $this->primary_color = $settings->primary_color;
        $this->secondary_color = $settings->secondary_color;
        $this->default_locale = $settings->default_locale;
        $this->default_currency = $settings->default_currency;
        $this->country_code = $settings->country_code;
        $this->shipping_countries = array_values(array_diff($settings->shipping_countries, [$settings->country_code]));
        $this->support_phone = $settings->support_phone;
        $this->support_whatsapp = $settings->support_whatsapp ?? '';
        $this->support_email = $settings->support_email;
    }

    /**
     * Picking the home country proposes its currency (the merchant can still change it).
     */
    public function updatedCountryCode(string $code): void
    {
        $country = Country::find($code);
        if ($country !== null) {
            $this->default_currency = $country->currency;
            $this->shipping_countries = array_values(array_diff($this->shipping_countries, [$country->code]));
        }
    }

    public function save(): void
    {
        $this->validate([
            'platform_name' => ['required', 'string', 'max:255'],
            'logo_url' => ['nullable', 'string', 'max:500'],
            'primary_color' => ['required', 'string', 'max:50'],
            'secondary_color' => ['required', 'string', 'max:50'],
            'default_locale' => ['required', 'in:ar,en'],
            'default_currency' => ['required', 'string', Rule::in(array_keys((array) config('currencies')))],
            'country_code' => ['required', 'string', Rule::in(array_keys((array) config('countries')))],
            'shipping_countries' => ['array'],
            'shipping_countries.*' => ['string', Rule::in(array_keys((array) config('countries')))],
            'support_phone' => ['nullable', 'string', 'max:30'],
            'support_whatsapp' => ['nullable', 'string', 'max:30'],
            'support_email' => ['nullable', 'email', 'max:255'],
        ]);

        $settings = GeneralSettings::load();

        $settings->platform_name = (string) $this->platform_name;
        $settings->logo_url = ! empty($this->logo_url) ? (string) $this->logo_url : null;
        $settings->primary_color = $this->primary_color;
        $settings->secondary_color = $this->secondary_color;
        $settings->default_locale = $this->default_locale;
        $currencyChanged = $settings->default_currency !== $this->default_currency;
        $settings->default_currency = $this->default_currency;
        $settings->country_code = $this->country_code;
        $settings->shipping_countries = array_values(array_diff($this->shipping_countries, [$this->country_code]));
        $settings->support_phone = (string) $this->support_phone;
        $settings->support_whatsapp = ! empty($this->support_whatsapp) ? (string) $this->support_whatsapp : null;
        $settings->support_email = (string) $this->support_email;

        $settings->save();

        if ($currencyChanged) {
            // The store sells in one currency: relabel Lunar's default currency to match.
            Currency::query()->where('default', true)->update([
                'code' => $this->default_currency,
                'name' => (string) config("currencies.{$this->default_currency}.name_en", $this->default_currency),
            ]);
        }

        Notification::make()
            ->title('تم حفظ إعدادات المتجر بنجاح')
            ->success()
            ->send();
    }
}
