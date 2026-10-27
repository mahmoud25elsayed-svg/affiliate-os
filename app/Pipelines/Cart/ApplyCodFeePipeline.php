<?php

namespace App\Pipelines\Cart;

use Closure;
use Lunar\Models\Cart;

class ApplyCodFeePipeline
{
    public function handle(Cart $cart, Closure $next): mixed
    {
        /** @var array<string, mixed> $meta */
        $meta = (array) ($cart->getAttribute('meta') ?? []);
        $meta['cod_fee_applied'] = true;
        $meta['cod_fee_amount_minor'] = 2500; // 25 EGP
        $cart->setAttribute('meta', $meta);

        return $next($cart);
    }
}
