<?php

declare(strict_types=1);

namespace App\Http\Controllers\Store;

use App\Domain\Affiliate\Settings\AffiliateProgramSettings;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StoreAffiliateSettingsController extends Controller
{
    public function show(): View
    {
        $settings = AffiliateProgramSettings::load();

        return view('store.affiliates.settings', [
            'settings' => $settings,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'commission_model' => ['required', 'string', 'in:markup,percentage,both'],
            'default_commission_rate' => ['required', 'integer', 'min:1', 'max:100'],
            'min_commission_minor' => ['required', 'integer', 'min:0'],
            'max_commission_minor' => ['required', 'integer', 'gt:min_commission_minor'],
            'min_selling_markup_minor' => ['required', 'integer', 'min:0'],
            'max_selling_markup_minor' => ['required', 'integer', 'gt:min_selling_markup_minor'],
            'min_payout_minor' => ['required', 'integer', 'min:1000'],
            'maker_checker_threshold_minor' => ['required', 'integer', 'min:1000'],
            'return_window_days' => ['required', 'integer', 'min:1', 'max:90'],
            'payout_days' => ['required', 'array'],
            'payout_days.*' => ['string', 'in:sunday,monday,tuesday,wednesday,thursday,friday,saturday'],
            'auto_approve_registrations' => ['boolean'],
            'show_customer_phone_to_affiliate' => ['boolean'],
            'default_daily_order_limit' => ['required', 'integer', 'min:1', 'max:500'],
            'release_mode' => ['required', 'string', 'in:return_window,courier_remittance,later_of_both'],
        ]);

        $validated['auto_approve_registrations'] = (bool) ($validated['auto_approve_registrations'] ?? false);
        $validated['show_customer_phone_to_affiliate'] = (bool) ($validated['show_customer_phone_to_affiliate'] ?? false);

        $settings = AffiliateProgramSettings::load();
        foreach ($validated as $key => $value) {
            if (property_exists($settings, $key)) {
                $settings->{$key} = $value;
            }
        }
        $settings->save();

        return redirect()->route('store.affiliates.settings.show')
            ->with('success', 'تم حفظ وتحديث إعدادات برنامج المسوقين وحدود العمولات بنجاح.');
    }
}
