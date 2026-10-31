<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Symfony\Component\HttpFoundation\Response;

/**
 * Livewire posts every component update to one shared route (/livewire/update), which is not
 * inside the tenant route group. Without this, storefront components (cart, checkout, customer
 * account, wishlist) ran without tenancy: the cart was saved under the "default" session key and
 * tenant-scoped queries returned nothing. Central-domain requests (platform panel) pass through.
 */
class InitializeTenancyForLivewire
{
    public function __construct(private readonly InitializeTenancyByDomain $byDomain) {}

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $central = (array) config('tenancy.central_domains', []);

        if (tenancy()->initialized || in_array($request->getHost(), $central, true)) {
            return $next($request);
        }

        /** @var Response */
        return $this->byDomain->handle($request, $next);
    }
}
