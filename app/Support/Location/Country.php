<?php

declare(strict_types=1);

namespace App\Support\Location;

use InvalidArgumentException;

/**
 * A supported country (config/countries.php): calling code, currency and mobile-number rule.
 */
final class Country
{
    public function __construct(
        public readonly string $code,
        public readonly string $nameAr,
        public readonly string $nameEn,
        public readonly string $dial,
        public readonly string $currency,
        public readonly string $trunk,
        public readonly string $mobilePattern,
        public readonly string $example,
        public readonly bool $arab,
    ) {}

    public static function find(?string $code): ?self
    {
        if ($code === null) {
            return null;
        }

        $code = strtoupper(trim($code));
        /** @var array<string, mixed>|null $row */
        $row = config("countries.{$code}");

        if (! is_array($row)) {
            return null;
        }

        return new self(
            code: $code,
            nameAr: (string) $row['name_ar'],
            nameEn: (string) $row['name_en'],
            dial: (string) $row['dial'],
            currency: (string) $row['currency'],
            trunk: (string) $row['trunk'],
            mobilePattern: (string) $row['mobile'],
            example: (string) $row['example'],
            arab: (bool) $row['arab'],
        );
    }

    public static function get(string $code): self
    {
        return self::find($code) ?? throw new InvalidArgumentException("Unsupported country [{$code}].");
    }

    /**
     * @return array<string, self> keyed by ISO code, Arab countries first
     */
    public static function all(): array
    {
        $countries = [];
        foreach (array_keys((array) config('countries', [])) as $code) {
            $countries[(string) $code] = self::get((string) $code);
        }

        return $countries;
    }

    /**
     * Select options: code => localized name, Arab countries first then international.
     *
     * @param  list<string>|null  $only
     * @return array<string, string>
     */
    public static function options(?array $only = null): array
    {
        $options = [];
        foreach (self::all() as $code => $country) {
            if ($only === null || in_array($code, $only, true)) {
                $options[$code] = $country->name();
            }
        }

        return $options;
    }

    public function name(?string $locale = null): string
    {
        return ($locale ?? app()->getLocale()) === 'en' ? $this->nameEn : $this->nameAr;
    }

    public function matchesMobile(string $nsn): bool
    {
        return preg_match('/^(?:'.$this->mobilePattern.')$/', $nsn) === 1;
    }
}
