<?php

declare(strict_types=1);

namespace App\Support;

/**
 * __() for places that need a guaranteed string (typed arrays, CSV rows). The app only uses
 * JSON translations, so a string key always resolves to a string; this just tells the type system.
 */
final class Translate
{
    /**
     * @param  array<string, mixed>  $replace
     */
    public static function t(string $key, array $replace = []): string
    {
        $value = __($key, $replace);

        return is_string($value) ? $value : $key;
    }
}
