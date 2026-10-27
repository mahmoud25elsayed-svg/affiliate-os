<?php

declare(strict_types=1);

namespace App\Support\Casts;

use App\Support\Money;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * @implements CastsAttributes<?Money, mixed>
 */
class MoneyCast implements CastsAttributes
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?Money
    {
        if ($value === null) {
            return null;
        }

        $currency = $attributes['currency_code'] ?? 'EGP';

        return new Money((int) $value, (string) $currency);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?int
    {
        if ($value === null) {
            return null;
        }

        if (is_float($value)) {
            throw new InvalidArgumentException('Floats cannot be cast to Money.');
        }

        if ($value instanceof Money) {
            return $value->amount;
        }

        if (is_int($value)) {
            return $value;
        }

        if (is_string($value)) {
            return Money::fromDecimalString($value)->amount;
        }

        throw new InvalidArgumentException('Invalid value provided for MoneyCast.');
    }
}
