<?php

declare(strict_types=1);

namespace App\Http\Controllers\Affiliate;

use App\Domain\Affiliate\Settings\AffiliateProgramSettings;
use App\Http\Controllers\Controller;
use App\Models\Affiliate;
use App\Models\Tenant;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AffiliateDashboardController extends Controller
{
    public function index(): View
    {
        /** @var Affiliate $affiliate */
        $affiliate = Auth::guard('affiliate')->user();
        $tenant = function_exists('tenancy') && tenancy()->initialized ? tenancy()->tenant : null;
        $settings = AffiliateProgramSettings::load();

        return view('affiliate.dashboard', [
            'affiliate' => $affiliate,
            'storeName' => $tenant instanceof Tenant ? $tenant->name : 'متجرنا',
            'settings' => $settings,
            'referralLink' => url('/partners/join?ref='.$affiliate->code),
        ]);
    }
}
