<?php

declare(strict_types=1);

namespace App\Support\Tenancy;

class ReservedSubdomains
{
    /**
     * @var list<string>
     */
    public const RESERVED = [
        'admin',
        'platform',
        'api',
        'app',
        'www',
        'store',
        'mail',
        'system',
        'root',
        'auth',
        'webhook',
        'billing',
        'support',
        'dashboard',
        'central',
        'lunar',
        'tenancy',
        'dev',
        'test',
        'staging',
        'production',
        'status',
        'help',
        'docs',
        'assets',
        'static',
        'cdn',
        'demo',
        'portal',
        'control',
        'affiliate',
        'affiliates',
    ];

    public static function isReserved(string $subdomain): bool
    {
        return in_array(strtolower(trim($subdomain)), self::RESERVED, true);
    }
}
