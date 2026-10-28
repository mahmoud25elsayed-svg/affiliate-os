<?php

declare(strict_types=1);

namespace App\Domain\Tenancy\Contracts;

interface DnsResolverInterface
{
    /**
     * @return list<string>
     */
    public function getTxtRecords(string $hostname): array;

    public function getCnameRecord(string $hostname): ?string;

    /**
     * @return list<string>
     */
    public function getARecords(string $hostname): array;
}
