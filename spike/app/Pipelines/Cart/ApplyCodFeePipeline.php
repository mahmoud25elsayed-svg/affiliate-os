<?php

namespace App\Pipelines\Cart;

use Closure;
use Lunar\Models\Contracts\Cart as CartContract;

class ApplyCodFeePipeline
{
    public function handle(CartContract $cart, Closure $next): mixed
    {
        $meta = (array) ($cart->meta ?? []);
        $meta['cod_fee_applied'] = true;
        $meta['cod_fee_amount_minor'] = 2500; // 25 EGP
        $cart->meta = $meta;

        return $next($cart);
    }
}
