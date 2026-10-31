<?php

declare(strict_types=1);

namespace App\Support;

use App\Support\Location\Market;
use InvalidArgumentException;
use JsonSerializable;
use Stringable;

/**
 * Integer money. `amount` is always in hundredths of the currency unit (piasters, cents,
 * halalas…) for every currency, including 3-decimal dinars and 0-decimal currencies; only
 * formatting follows the currency's display decimals (config/currencies.php).
 */
final class Money implements JsonSerializable, Stringable
{
    public readonly string $currency;

    /**
     * @param  int  $amount  Amount in hundredths of the currency unit
     * @param  string|null  $currency  ISO-4217 code; defaults to the current store's currency
     */
    public function __construct(
        public readonly int $amount,
        ?string $currency = null
    ) {
        $this->currency = strtoupper($currency ?? self::defaultCurrency());
    }

    /**
     * Named constructor from integer minor units (hundredths).
     */
    public static function fromMinor(int $amount, ?string $currency = null): self
    {
        return new self($amount, $currency);
    }

    /**
     * Named constructor from decimal string (e.g. "12.50" -> 1250) strictly without floats.
     */
    public static function fromDecimalString(string $decimal, ?string $currency = null): self
    {
        $decimal = trim($decimal);

        if (! preg_match('/^-?\d+(\.\d+)?$/', $decimal)) {
            throw new InvalidArgumentException("Invalid decimal string for Money: [{$decimal}]");
        }

        $isNegative = str_starts_with($decimal, '-');
        $clean = ltrim($decimal, '-');

        $parts = explode('.', $clean);
        $whole = $parts[0];
        $fraction = $parts[1] ?? '';

        // Normalize fraction to 2 decimal places (standard for EGP / USD / EUR)
        $fraction = substr(str_pad($fraction, 2, '0'), 0, 2);

        $minor = ((int) $whole * 100) + (int) $fraction;

        if ($isNegative) {
            $minor = -$minor;
        }

        return new self($minor, $currency);
    }

    /**
     * Minor units of an Eloquent money attribute, whether Lunar cast it to a Price/Money object
     * (`->value` / `->amount`) or it is still a raw integer column. Null becomes 0.
     */
    public static function minorOf(mixed $value): int
    {
        if (is_object($value)) {
            if (property_exists($value, 'value')) {
                return (int) $value->value;
            }
            if ($value instanceof self) {
                return $value->amount;
            }
        }

        return is_numeric($value) ? (int) $value : 0;
    }

    /**
     * Strictly rejects floats.
     *
     * @throws InvalidArgumentException
     */
    public static function fromFloat(mixed $float): never
    {
        throw new InvalidArgumentException('Floats are strictly forbidden in financial calculations. Pass integer minor units or decimal strings.');
    }

    public function add(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->amount + $other->amount, $this->currency);
    }

    public function subtract(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->amount - $other->amount, $this->currency);
    }

    public function multiply(int $multiplier): self
    {
        return new self($this->amount * $multiplier, $this->currency);
    }

    /**
     * Allocate amount across ratios without losing remainder.
     * Example: allocate(100, [1, 1, 1]) => [34, 33, 33]
     *
     * @param  array<int, int>  $ratios
     * @return array<int, self>
     */
    public function allocate(array $ratios): array
    {
        if (empty($ratios)) {
            throw new InvalidArgumentException('Cannot allocate to empty ratios.');
        }

        $totalRatio = array_sum($ratios);

        if ($totalRatio <= 0) {
            throw new InvalidArgumentException('Total ratio must be positive.');
        }

        $remainder = $this->amount;
        $results = [];

        foreach ($ratios as $ratio) {
            if ($ratio < 0) {
                throw new InvalidArgumentException('Ratios must be non-negative.');
            }

            $share = intdiv($this->amount * $ratio, $totalRatio);
            $results[] = $share;
            $remainder -= $share;
        }

        // Distribute remainder 1 by 1 to elements with the largest ratios/first elements
        for ($i = 0; $i < $remainder; $i++) {
            $results[$i % count($results)]++;
        }

        return array_map(fn (int $amount) => new self($amount, $this->currency), $results);
    }

    public function equals(self $other): bool
    {
        return $this->amount === $other->amount && $this->currency === $other->currency;
    }

    public function greaterThan(self $other): bool
    {
        $this->assertSameCurrency($other);

        return $this->amount > $other->amount;
    }

    public function greaterThanOrEqual(self $other): bool
    {
        $this->assertSameCurrency($other);

        return $this->amount >= $other->amount;
    }

    public function lessThan(self $other): bool
    {
        $this->assertSameCurrency($other);

        return $this->amount < $other->amount;
    }

    public function lessThanOrEqual(self $other): bool
    {
        $this->assertSameCurrency($other);

        return $this->amount <= $other->amount;
    }

    public function isZero(): bool
    {
        return $this->amount === 0;
    }

    public function isPositive(): bool
    {
        return $this->amount > 0;
    }

    public function isNegative(): bool
    {
        return $this->amount < 0;
    }

    public function toDecimalString(): string
    {
        $sign = $this->amount < 0 ? '-' : '';
        $abs = abs($this->amount);
        $whole = intdiv($abs, 100);
        $fraction = str_pad((string) ($abs % 100), 2, '0', STR_PAD_LEFT);

        return "{$sign}{$whole}.{$fraction}";
    }

    /**
     * Human format in the currency's own symbol and display decimals, e.g. "1,250.50 ج.م",
     * "12.500 د.ك", "250,000 د.ع", "$ 19.99". Locale defaults to the current app locale.
     */
    public function format(?string $locale = null): string
    {
        $locale ??= app()->getLocale();
        $meta = (array) config("currencies.{$this->currency}", []);
        $decimals = (int) ($meta['display_decimals'] ?? 2);

        $abs = abs($this->amount);
        $whole = number_format(intdiv($abs, 100), 0, '.', ',');
        $fraction = str_pad((string) ($abs % 100), 2, '0', STR_PAD_LEFT);
        $number = ($this->amount < 0 ? '-' : '').$whole.match (true) {
            $decimals <= 0 => '',
            $decimals === 2 => '.'.$fraction,
            default => '.'.str_pad($fraction, $decimals, '0'),
        };

        if ($locale === 'ar') {
            return $number.' '.($meta['symbol_ar'] ?? $this->currency);
        }

        return ($meta['symbol_en'] ?? $this->currency).' '.$number;
    }

    /**
     * Symbol of a currency (the store's by default) for labels such as "Price (ج.م)".
     */
    public static function symbol(?string $currency = null, ?string $locale = null): string
    {
        $currency = strtoupper($currency ?? self::defaultCurrency());
        $key = ($locale ?? app()->getLocale()) === 'ar' ? 'symbol_ar' : 'symbol_en';

        return (string) config("currencies.{$currency}.{$key}", $currency);
    }

    /**
     * Format any stored money value (Lunar Price object, int minor units, null) in the store's
     * currency. Used by views instead of number_format($x / 100, 2) . ' ج.م'.
     */
    public static function show(mixed $value, ?string $currency = null): string
    {
        return self::fromMinor(self::minorOf($value), $currency)->format();
    }

    /**
     * The current store's currency, or the platform default outside a store.
     */
    public static function defaultCurrency(): string
    {
        try {
            return Market::currency();
        } catch (\Throwable) {
            return 'EGP';
        }
    }

    private function assertSameCurrency(self $other): void
    {
        if ($this->currency !== $other->currency) {
            throw new InvalidArgumentException("Currency mismatch: [{$this->currency}] vs [{$other->currency}].");
        }
    }

    /**
     * @return array{amount: int, currency: string, decimal: string, formatted: string}
     */
    public function jsonSerialize(): array
    {
        return [
            'amount' => $this->amount,
            'currency' => $this->currency,
            'decimal' => $this->toDecimalString(),
            'formatted' => $this->format(),
        ];
    }

    public function __toString(): string
    {
        return $this->format();
    }
}
