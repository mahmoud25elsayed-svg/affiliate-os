<?php

declare(strict_types=1);

namespace App\Support\Phone;

use App\Support\Location\Country;
use App\Support\Location\Market;
use InvalidArgumentException;
use JsonSerializable;
use Stringable;

/**
 * Mobile number in any supported country (config/countries.php), normalised to E.164.
 *
 * Local input ("0512345678") is read in the given country, defaulting to the current store's
 * country; international input ("+966…", "00966…") picks the country from its calling code.
 * The hash is HMAC(E.164), identical to the former EgyptianPhone hash, so stored hashes of
 * Egyptian numbers keep matching.
 */
final class PhoneNumber implements JsonSerializable, Stringable
{
    private const LOCAL_DIGITS = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩', '۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];

    private const WESTERN_DIGITS = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];

    public function __construct(
        public readonly string $e164,
        public readonly string $national,
        public readonly string $country,
    ) {}

    /**
     * @param  string|null  $country  ISO code used for local numbers; defaults to the store's country
     * @param  list<string>|null  $allowed  restrict accepted countries (e.g. the store's shipping countries)
     *
     * @throws InvalidArgumentException
     */
    public static function parse(string $input, ?string $country = null, ?array $allowed = null): self
    {
        $home = Country::find($country) ?? Market::country();
        $raw = str_replace(self::LOCAL_DIGITS, self::WESTERN_DIGITS, trim($input));
        $international = str_starts_with($raw, '+') || str_starts_with($raw, '00');
        $digits = preg_replace('/\D/', '', $raw) ?? '';

        if ($international) {
            $digits = str_starts_with($raw, '00') ? substr($digits, 2) : $digits;

            return self::fromInternational($digits, $home, $allowed)
                ?? throw new InvalidArgumentException("Invalid international mobile number: [{$input}]");
        }

        // Local format, or the calling code typed without "+".
        $local = self::fromLocal($digits, $home);
        if ($local !== null && self::isAllowed($local->country, $allowed)) {
            return $local;
        }

        return self::fromInternational($digits, $home, $allowed)
            ?? throw new InvalidArgumentException("Invalid {$home->nameEn} mobile number: [{$input}]");
    }

    /**
     * @param  list<string>|null  $allowed
     */
    public static function tryParse(?string $input, ?string $country = null, ?array $allowed = null): ?self
    {
        if ($input === null || trim($input) === '') {
            return null;
        }

        try {
            return self::parse($input, $country, $allowed);
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    /**
     * @param  list<string>|null  $allowed
     */
    public static function isValid(?string $input, ?string $country = null, ?array $allowed = null): bool
    {
        return self::tryParse($input, $country, $allowed) !== null;
    }

    /**
     * Localised validation message with an example number for the store's country.
     */
    public static function invalidMessage(?string $country = null): string
    {
        $c = Country::find($country) ?? Market::country();

        return __('رقم الهاتف غير صالح. اكتب رقم موبايل :country صحيحاً مثل :example أو بالصيغة الدولية +:dial', [
            'country' => $c->name(),
            'example' => $c->example,
            'dial' => $c->dial,
        ]);
    }

    public function hash(?string $key = null): string
    {
        $secret = $key ?? (string) config('app.key', 'affiliate-os-default-hmac-key');

        return hash_hmac('sha256', $this->e164, $secret);
    }

    private static function fromLocal(string $digits, Country $country): ?self
    {
        $nsn = ($country->trunk !== '' && str_starts_with($digits, $country->trunk))
            ? substr($digits, strlen($country->trunk))
            : $digits;

        return $country->matchesMobile($nsn) ? self::build($country, $nsn) : null;
    }

    /**
     * @param  list<string>|null  $allowed
     */
    private static function fromInternational(string $digits, Country $home, ?array $allowed): ?self
    {
        // Home country first so NANP "+1" resolves to the store's own US/CA.
        $candidates = Country::all();
        uasort($candidates, fn (Country $a, Country $b): int => ($b->code === $home->code) <=> ($a->code === $home->code)
            ?: strlen($b->dial) <=> strlen($a->dial));

        foreach ($candidates as $country) {
            if (! self::isAllowed($country->code, $allowed) || ! str_starts_with($digits, $country->dial)) {
                continue;
            }

            $nsn = substr($digits, strlen($country->dial));
            // Tolerate a trunk "0" typed after the calling code: +20 010…
            if ($country->trunk === '0' && str_starts_with($nsn, '0')) {
                $nsn = substr($nsn, 1);
            }

            if ($country->matchesMobile($nsn)) {
                return self::build($country, $nsn);
            }
        }

        return null;
    }

    private static function build(Country $country, string $nsn): self
    {
        return new self('+'.$country->dial.$nsn, $country->trunk.$nsn, $country->code);
    }

    /**
     * @param  list<string>|null  $allowed
     */
    private static function isAllowed(string $code, ?array $allowed): bool
    {
        return $allowed === null || in_array($code, $allowed, true);
    }

    /**
     * @return array{e164: string, national: string, country: string}
     */
    public function jsonSerialize(): array
    {
        return ['e164' => $this->e164, 'national' => $this->national, 'country' => $this->country];
    }

    public function __toString(): string
    {
        return $this->e164;
    }
}
