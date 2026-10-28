<?php

declare(strict_types=1);

namespace App\Filament\Store\Pages;

use App\Domain\Affiliate\Settings\AffiliateProgramSettings;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class ManageAffiliateProgramSettings extends Page
{
    protected string $view = 'filament.store.pages.manage-affiliate-program-settings';

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-adjustments-horizontal';

    protected static ?string $navigationLabel = 'إعدادات برنامج المسوقين';

    protected static ?string $title = 'إعدادات وقواعد برنامج الأفلييت';

    protected static \UnitEnum|string|null $navigationGroup = 'برنامج الأفلييت';

    protected static ?int $navigationSort = 2;

    public string $commission_model = 'markup';

    public int $default_commission_rate = 10;

    public float $min_commission = 10.00;

    public float $max_commission = 1000.00;

    public float $min_selling_markup = 20.00;

    public float $max_selling_markup = 2000.00;

    public int $return_window_days = 14;

    public float $min_payout = 100.00;

    /**
     * @var list<string>
     */
    public array $payout_days = ['sunday', 'wednesday'];

    public bool $auto_approve_registrations = false;

    public bool $show_customer_phone_to_affiliate = false;

    public int $default_daily_order_limit = 50;

    public string $release_mode = 'later_of_both';

    public int $payout_freeze_hours_on_change = 24;

    public function mount(): void
    {
        $settings = AffiliateProgramSettings::load();

        $this->commission_model = $settings->commission_model;
        $this->default_commission_rate = $settings->default_commission_rate;
        $this->min_commission = $settings->min_commission_minor / 100;
        $this->max_commission = $settings->max_commission_minor / 100;
        $this->min_selling_markup = $settings->min_selling_markup_minor / 100;
        $this->max_selling_markup = $settings->max_selling_markup_minor / 100;
        $this->return_window_days = $settings->return_window_days;
        $this->min_payout = $settings->min_payout_minor / 100;
        $this->payout_days = $settings->payout_days;
        $this->auto_approve_registrations = $settings->auto_approve_registrations;
        $this->show_customer_phone_to_affiliate = $settings->show_customer_phone_to_affiliate;
        $this->default_daily_order_limit = $settings->default_daily_order_limit;
        $this->release_mode = $settings->release_mode;
        $this->payout_freeze_hours_on_change = $settings->payout_freeze_hours_on_change;
    }

    public function save(): void
    {
        $this->validate([
            'commission_model' => ['required', 'in:markup,percentage,both'],
            'default_commission_rate' => ['required', 'integer', 'min:1', 'max:100'],
            'min_commission' => ['required', 'numeric', 'min:0'],
            'max_commission' => ['required', 'numeric', 'gte:min_commission'],
            'min_selling_markup' => ['required', 'numeric', 'min:0'],
            'max_selling_markup' => ['required', 'numeric', 'gte:min_selling_markup'],
            'return_window_days' => ['required', 'integer', 'min:0', 'max:90'],
            'min_payout' => ['required', 'numeric', 'min:10'],
            'payout_days' => ['required', 'array', 'min:1'],
            'default_daily_order_limit' => ['required', 'integer', 'min:1', 'max:1000'],
            'release_mode' => ['required', 'in:return_window,courier_remittance,later_of_both'],
            'payout_freeze_hours_on_change' => ['required', 'integer', 'min:0', 'max:168'],
        ]);

        $settings = AffiliateProgramSettings::load();

        $settings->commission_model = $this->commission_model;
        $settings->default_commission_rate = (int) $this->default_commission_rate;
        $settings->min_commission_minor = (int) round($this->min_commission * 100);
        $settings->max_commission_minor = (int) round($this->max_commission * 100);
        $settings->min_selling_markup_minor = (int) round($this->min_selling_markup * 100);
        $settings->max_selling_markup_minor = (int) round($this->max_selling_markup * 100);
        $settings->return_window_days = (int) $this->return_window_days;
        $settings->min_payout_minor = (int) round($this->min_payout * 100);
        $settings->payout_days = $this->payout_days;
        $settings->auto_approve_registrations = (bool) $this->auto_approve_registrations;
        $settings->show_customer_phone_to_affiliate = (bool) $this->show_customer_phone_to_affiliate;
        $settings->default_daily_order_limit = (int) $this->default_daily_order_limit;
        $settings->release_mode = $this->release_mode;
        $settings->payout_freeze_hours_on_change = (int) $this->payout_freeze_hours_on_change;

        $settings->save();

        Notification::make()
            ->title('تم حفظ إعدادات برنامج المسوقين بنجاح')
            ->success()
            ->send();
    }
}
