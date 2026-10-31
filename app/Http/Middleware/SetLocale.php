<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Settings\GeneralSettings;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\Response;

/**
 * Chooses the interface language: an explicit ?lang= switch (remembered in the session), else
 * the visitor's earlier choice, else the store's default language (inside a tenant), else the
 * platform default. Registered on the web group and again after tenancy is initialised (tenant
 * routes, store panel, Livewire) so the store's own default applies there.
 */
class SetLocale
{
    public const SUPPORTED = ['ar', 'en'];

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $requested = $request->query('lang');
        $session = $request->hasSession() ? $request->session() : null;

        if (is_string($requested) && in_array($requested, self::SUPPORTED, true)) {
            $session?->put('locale', $requested);
            $locale = $requested;
        } else {
            $remembered = $session?->get('locale');
            $locale = is_string($remembered) && in_array($remembered, self::SUPPORTED, true)
                ? $remembered
                : $this->defaultLocale();
        }

        app()->setLocale($locale);
        Carbon::setLocale($locale);

        return $next($request);
    }

    private function defaultLocale(): string
    {
        $default = function_exists('tenancy') && tenancy()->initialized
            ? GeneralSettings::load()->default_locale
            : (string) config('app.locale', 'ar');

        return in_array($default, self::SUPPORTED, true) ? $default : 'ar';
    }
}
