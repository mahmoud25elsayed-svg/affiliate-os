<?php

declare(strict_types=1);

namespace App\Filament\Store\Pages;

use App\Models\TenantShippingRate;
use App\Support\Location\Governorate;
use App\Support\Settings\ShippingSettings;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;

class ManageShippingSettings extends Page
{
    protected string $view = 'filament.store.pages.manage-shipping-settings';

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-truck';

    public static function getNavigationLabel(): string
    {

        return __('إعدادات الشحن');

    }

    public function getTitle(): string|Htmlable
    {
        return __('إعدادات الشحن والتوصيل');
    }

    public float $default_rate_egp = 50.0;

    public float $free_shipping_threshold_egp = 1000.0;

    public float $cod_fee_egp = 15.0;

    public bool $allow_cod = true;

    public string $default_delivery_time_ar = 'خلال 2-4 أيام عمل';

    /**
     * @var array<int, array{rate_egp: float, delivery_time_ar: string, is_active: bool}>
     */
    public array $governorate_rates = [];

    public function mount(): void
    {
        $settings = ShippingSettings::load();

        $this->default_rate_egp = $settings->default_rate_minor / 100;
        $this->free_shipping_threshold_egp = $settings->free_shipping_threshold_minor / 100;
        $this->cod_fee_egp = $settings->cod_fee_minor / 100;
        $this->allow_cod = $settings->allow_cod;
        $this->default_delivery_time_ar = $settings->default_delivery_time_ar;

        $governorates = Governorate::forCountries();
        $rates = TenantShippingRate::all()->keyBy('governorate_id');

        foreach ($governorates as $gov) {
            $existing = $rates->get($gov->id);
            $this->governorate_rates[$gov->id] = [
                'rate_egp' => $existing ? $existing->rate_minor / 100 : $this->default_rate_egp,
                'delivery_time_ar' => $existing ? $existing->delivery_time_ar : $this->default_delivery_time_ar,
                'is_active' => $existing ? (bool) $existing->is_active : true,
            ];
        }
    }

    public function save(): void
    {
        $this->validate([
            'default_rate_egp' => ['required', 'numeric', 'min:0'],
            'free_shipping_threshold_egp' => ['required', 'numeric', 'min:0'],
            'cod_fee_egp' => ['required', 'numeric', 'min:0'],
            'allow_cod' => ['boolean'],
            'default_delivery_time_ar' => ['required', 'string', 'max:100'],
        ]);

        $settings = ShippingSettings::load();
        $settings->default_rate_minor = (int) round($this->default_rate_egp * 100);
        $settings->free_shipping_threshold_minor = (int) round($this->free_shipping_threshold_egp * 100);
        $settings->cod_fee_minor = (int) round($this->cod_fee_egp * 100);
        $settings->allow_cod = $this->allow_cod;
        $settings->default_delivery_time_ar = $this->default_delivery_time_ar;
        $settings->save();

        // Update governorate rates
        foreach ($this->governorate_rates as $govId => $data) {
            TenantShippingRate::updateOrCreate(
                ['governorate_id' => $govId],
                [
                    'rate_minor' => (int) round(((float) $data['rate_egp']) * 100),
                    'delivery_time_ar' => $data['delivery_time_ar'],
                    'is_active' => (bool) $data['is_active'],
                ]
            );
        }

        Notification::make()
            ->title(__('تم حفظ إعدادات الشحن بنجاح'))
            ->success()
            ->send();
    }
}
