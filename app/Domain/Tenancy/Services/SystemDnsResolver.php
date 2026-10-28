<?php

declare(strict_types=1);

namespace App\Domain\Tenancy\Services;

use App\Domain\Tenancy\Contracts\DnsResolverInterface;

class SystemDnsResolver implements DnsResolverInterface
{
    /**
     * @return list<string>
     */
    public function getTxtRecords(string $hostname): array
    {
        $records = @dns_get_record($hostname, DNS_TXT);
        if (! is_array($records)) {
            return [];
        }

        $results = [];
        foreach ($records as $record) {
            if (isset($record['txt']) && is_string($record['txt'])) {
                $results[] = $record['txt'];
            }
        }

        return $results;
    }

    public function getCnameRecord(string $hostname): ?string
    {
        $records = @dns_get_record($hostname, DNS_CNAME);
        if (! is_array($records) || empty($records)) {
            return null;
        }

        $first = $records[0];

        return isset($first['target']) && is_string($first['target']) ? rtrim($first['target'], '.') : null;
    }

    /**
     * @return list<string>
     */
    public function getARecords(string $hostname): array
    {
        $records = @dns_get_record($hostname, DNS_A);
        if (! is_array($records)) {
            return [];
        }

        $results = [];
        foreach ($records as $record) {
            if (isset($record['ip']) && is_string($record['ip'])) {
                $results[] = $record['ip'];
            }
        }

        return $results;
    }
}
