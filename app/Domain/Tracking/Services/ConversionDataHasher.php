<?php

declare(strict_types=1);

namespace App\Domain\Tracking\Services;

class ConversionDataHasher
{
    /**
     * Hash string with SHA256 after trimming and lowercasing.
     */
    public static function hash(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $normalized = mb_strtolower(trim($value), 'UTF-8');

        return hash('sha256', $normalized);
    }

    /**
     * Hash Email address per Meta / TikTok specs.
     */
    public static function hashEmail(?string $email): ?string
    {
        return static::hash($email);
    }

    /**
     * Hash Phone number: strips non-digit characters (including +), keeps country code.
     * Example: "+20 101 234 5678" -> "201012345678" -> sha256.
     */
    public static function hashPhone(?string $phone): ?string
    {
        if ($phone === null || trim($phone) === '') {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $phone);
        if (empty($digits)) {
            return null;
        }

        // If Egyptian local format without country code (010... -> 2010...)
        if (str_starts_with($digits, '01') && strlen($digits) === 11) {
            $digits = '2'.substr($digits, 0); // e.g. 2010...
        }

        return hash('sha256', $digits);
    }

    /**
     * Hash city: lowercased, spaces removed.
     */
    public static function hashCity(?string $city): ?string
    {
        if ($city === null || trim($city) === '') {
            return null;
        }

        $normalized = mb_strtolower(preg_replace('/\s+/', '', trim($city)) ?? '', 'UTF-8');

        return hash('sha256', $normalized);
    }
}
