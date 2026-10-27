<?php

declare(strict_types=1);

namespace App\Support;

use InvalidArgumentException;
use JsonSerializable;
use Stringable;

final class Money implements JsonSerializable, Stringable
{
    /**
     * @param  int  $amount  Amount in minor units (e.g., piasters / cents)
     * @param  string  $currency  ISO-4217 Currency Code
     */
    public function __construct(
        public readonly int $amount,
        public readonly string $currency = 'EGP'
    ) {}

    /**
     * Named constructor from integer piasters/minor units.
     */
    public static function fromMinor(int $amount, string $currency = 'EGP'): self
    {
        return new self($amount, strtoupper($currency));
    }

    /**
     * Named constructor from decimal string (e.g. "12.50" -> 1250) strictly without floats.
     */
    public static function fromDecimalString(string $decimal, string $currency = 'EGP'): self
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

        return new self($minor, strtoupper($currency));
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

    public function format(string $locale = 'ar'): string
    {
        $decimal = $this->toDecimalString();

        if ($locale === 'ar') {
            $currencyLabel = match ($this->currency) {
                'EGP' => 'ج.م',
                'USD' => '$',
                'EUR' => '€',
                'SAR' => 'ر.س',
                default => $this->currency,
            };

            return "{$decimal} {$currencyLabel}";
        }

        return "{$this->currency} {$decimal}";
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
