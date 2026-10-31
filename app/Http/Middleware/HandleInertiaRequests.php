<?php

namespace App\Http\Middleware;

use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $locale = app()->getLocale();
        $tenant = function_exists('tenancy') && tenancy()->initialized ? tenancy()->tenant : null;

        return [
            ...parent::share($request),
            'locale' => $locale,
            'direction' => in_array($locale, ['ar', 'fa', 'ur', 'he']) ? 'rtl' : 'ltr',
            'auth' => [
                'user' => $request->user(),
                'affiliate' => Auth::guard('affiliate')->user(),
            ],
            'currency' => [
                'code' => Money::defaultCurrency(),
                'symbol' => Money::symbol(),
                'decimals' => (int) config('currencies.'.Money::defaultCurrency().'.display_decimals', 2),
            ],
            'tenant' => $tenant ? [
                'id' => (string) $tenant->getKey(),
                'name' => (string) ($tenant->getAttribute('name') ?? __('متجرنا')),
            ] : null,
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
        ];
    }
}
