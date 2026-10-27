<?php

declare(strict_types=1);

namespace App\Support\Phone;

use InvalidArgumentException;
use JsonSerializable;
use Stringable;

final class EgyptianPhone implements JsonSerializable, Stringable
{
    private const ARABIC_INDIC_DIGITS = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];

    private const EASTERN_ARABIC_DIGITS = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];

    private const WESTERN_DIGITS = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];

    /**
     * @param  string  $e164  Full E.164 formatted number: +201012345678
     * @param  string  $national  National formatted number: 01012345678
     */
    public function __construct(
        public readonly string $e164,
        public readonly string $national
    ) {}

    /**
     * Parse and validate Egyptian phone number.
     *
     * @throws InvalidArgumentException
     */
    public static function parse(string $input): self
    {
        $normalized = self::normalizeRawString($input);

        if (! self::isValidNormalized($normalized)) {
            throw new InvalidArgumentException("Invalid Egyptian mobile phone number: [{$input}]");
        }

        $national = '0'.substr($normalized, 2); // e.g. 01012345678
        $e164 = '+'.$normalized;                 // e.g. +201012345678

        return new self($e164, $national);
    }

    public static function tryParse(?string $input): ?self
    {
        if ($input === null || trim($input) === '') {
            return null;
        }

        try {
            return self::parse($input);
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    public static function isValid(?string $input): bool
    {
        if ($input === null || trim($input) === '') {
            return false;
        }

        return self::isValidNormalized(self::normalizeRawString($input));
    }

    /**
     * Compute deterministic HMAC-SHA256 hash for privacy-safe indexing/reputation tracking.
     */
    public function hash(?string $key = null): string
    {
        $secret = $key ?? (string) config('app.key', 'affiliate-os-default-hmac-key');

        return hash_hmac('sha256', $this->e164, $secret);
    }

    /**
     * Identify mobile telecom provider.
     */
    public function getNetwork(): string
    {
        $prefix = substr($this->national, 0, 3);

        return match ($prefix) {
            '010' => 'Vodafone',
            '011' => 'Etisalat',
            '012' => 'Orange',
            '015' => 'WE',
            default => 'Unknown',
        };
    }

    private static function normalizeRawString(string $input): string
    {
        // Convert Arabic/Eastern digits to standard western digits
        $standardized = str_replace(
            self::ARABIC_INDIC_DIGITS,
            self::WESTERN_DIGITS,
            $input
        );
        $standardized = str_replace(
            self::EASTERN_ARABIC_DIGITS,
            self::WESTERN_DIGITS,
            $standardized
        );

        // Keep only digits
        $digitsOnly = preg_replace('/\D/', '', $standardized) ?? '';

        // Case 1: starts with 0020... -> remove leading 00
        if (str_starts_with($digitsOnly, '0020')) {
            return substr($digitsOnly, 2);
        }

        // Case 2: starts with 20... -> 201012345678
        if (str_starts_with($digitsOnly, '20') && strlen($digitsOnly) === 12) {
            return $digitsOnly;
        }

        // Case 3: starts with 01... (11 digits: 01012345678) -> convert to 201012345678
        if (str_starts_with($digitsOnly, '01') && strlen($digitsOnly) === 11) {
            return '20'.substr($digitsOnly, 1);
        }

        // Case 4: 10 digits without leading 0 (1012345678)
        if (preg_match('/^1[0125]\d{8}$/', $digitsOnly)) {
            return '20'.$digitsOnly;
        }

        return $digitsOnly;
    }

    private static function isValidNormalized(string $normalized): bool
    {
        // Must be exactly 12 digits: 20 + (10|11|12|15) + 8 digits
        return (bool) preg_match('/^201[0125]\d{8}$/', $normalized);
    }

    /**
     * @return array{e164: string, national: string, network: ?string}
     */
    public function jsonSerialize(): array
    {
        return [
            'e164' => $this->e164,
            'national' => $this->national,
            'network' => $this->getNetwork(),
        ];
    }

    public function __toString(): string
    {
        return $this->e164;
    }
}
