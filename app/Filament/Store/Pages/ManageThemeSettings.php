<?php

declare(strict_types=1);

namespace App\Filament\Store\Pages;

use App\Support\Settings\ThemeSettings;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class ManageThemeSettings extends Page
{
    protected string $view = 'filament.store.pages.manage-theme-settings';

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-paint-brush';

    protected static ?string $navigationLabel = 'تخصيص الواجهة والثيم';

    protected static ?string $title = 'تخصيص واجهة المتجر والثيم';

    public string $primary_color = '#4f46e5';

    public string $secondary_color = '#06b6d4';

    public string $accent_color = '#f59e0b';

    public string $font_family = 'Cairo';

    public ?string $logo_url = '';

    public ?string $favicon_url = '';

    public string $hero_headline = '';

    public string $hero_subheadline = '';

    public ?string $hero_image_url = '';

    public string $hero_button_text = 'تسوق الآن';

    public string $hero_button_url = '/products';

    public bool $show_hero_banner = true;

    public bool $show_featured_categories = true;

    public bool $show_featured_products = true;

    public bool $show_offers_banner = true;

    public bool $show_latest_products = true;

    public string $about_us = '';

    public string $terms_and_conditions = '';

    public string $privacy_policy = '';

    public string $shipping_and_returns = '';

    public function mount(): void
    {
        $settings = ThemeSettings::load();

        $this->primary_color = $settings->primary_color;
        $this->secondary_color = $settings->secondary_color;
        $this->accent_color = $settings->accent_color;
        $this->font_family = $settings->font_family;
        $this->logo_url = $settings->logo_url ?? '';
        $this->favicon_url = $settings->favicon_url ?? '';
        $this->hero_headline = $settings->hero_headline;
        $this->hero_subheadline = $settings->hero_subheadline;
        $this->hero_image_url = $settings->hero_image_url ?? '';
        $this->hero_button_text = $settings->hero_button_text;
        $this->hero_button_url = $settings->hero_button_url;
        $this->show_hero_banner = $settings->show_hero_banner;
        $this->show_featured_categories = $settings->show_featured_categories;
        $this->show_featured_products = $settings->show_featured_products;
        $this->show_offers_banner = $settings->show_offers_banner;
        $this->show_latest_products = $settings->show_latest_products;
        $this->about_us = $settings->about_us;
        $this->terms_and_conditions = $settings->terms_and_conditions;
        $this->privacy_policy = $settings->privacy_policy;
        $this->shipping_and_returns = $settings->shipping_and_returns;
    }

    public function save(): void
    {
        $this->validate([
            'primary_color' => ['required', 'string', 'max:50'],
            'secondary_color' => ['required', 'string', 'max:50'],
            'accent_color' => ['required', 'string', 'max:50'],
            'font_family' => ['required', 'string', 'in:Cairo,Tajawal,Readex Pro,Almarai,Alexandria,IBM Plex Sans Arabic'],
            'logo_url' => ['nullable', 'string', 'max:500'],
            'favicon_url' => ['nullable', 'string', 'max:500'],
            'hero_headline' => ['required', 'string', 'max:255'],
            'hero_subheadline' => ['required', 'string', 'max:500'],
            'hero_image_url' => ['nullable', 'string', 'max:500'],
            'hero_button_text' => ['required', 'string', 'max:50'],
            'hero_button_url' => ['required', 'string', 'max:255'],
            'about_us' => ['nullable', 'string'],
            'terms_and_conditions' => ['nullable', 'string'],
            'privacy_policy' => ['nullable', 'string'],
            'shipping_and_returns' => ['nullable', 'string'],
        ]);

        $settings = ThemeSettings::load();

        $settings->primary_color = $this->primary_color;
        $settings->secondary_color = $this->secondary_color;
        $settings->accent_color = $this->accent_color;
        $settings->font_family = $this->font_family;
        $settings->logo_url = ! empty($this->logo_url) ? (string) $this->logo_url : null;
        $settings->favicon_url = ! empty($this->favicon_url) ? (string) $this->favicon_url : null;
        $settings->hero_headline = $this->hero_headline;
        $settings->hero_subheadline = $this->hero_subheadline;
        $settings->hero_image_url = ! empty($this->hero_image_url) ? (string) $this->hero_image_url : null;
        $settings->hero_button_text = $this->hero_button_text;
        $settings->hero_button_url = $this->hero_button_url;
        $settings->show_hero_banner = (bool) $this->show_hero_banner;
        $settings->show_featured_categories = (bool) $this->show_featured_categories;
        $settings->show_featured_products = (bool) $this->show_featured_products;
        $settings->show_offers_banner = (bool) $this->show_offers_banner;
        $settings->show_latest_products = (bool) $this->show_latest_products;
        $settings->about_us = $this->about_us;
        $settings->terms_and_conditions = $this->terms_and_conditions;
        $settings->privacy_policy = $this->privacy_policy;
        $settings->shipping_and_returns = $this->shipping_and_returns;

        $settings->save();

        Notification::make()
            ->title('تم حفظ إعدادات المظهر والثيم بنجاح')
            ->success()
            ->send();
    }
}
