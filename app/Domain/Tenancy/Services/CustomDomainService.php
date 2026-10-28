<?php

declare(strict_types=1);

namespace App\Domain\Tenancy\Services;

use App\Domain\Tenancy\Contracts\DnsResolverInterface;
use App\Domain\Tenancy\Contracts\HostingerClientInterface;
use App\Models\Domain;
use App\Models\Tenant;
use App\Support\Billing\Features;
use Illuminate\Support\Str;
use InvalidArgumentException;

class CustomDomainService
{
    public function __construct(
        protected DnsResolverInterface $dnsResolver,
        protected HostingerClientInterface $hostingerClient
    ) {}

    /**
     * Normalize hostname (strip protocols, paths, ports, lowercase, trim).
     */
    public function normalizeDomain(string $rawDomain): string
    {
        $domain = trim($rawDomain);
        $domain = preg_replace('#^https?://#i', '', $domain) ?? $domain;
        $domain = preg_replace('#/.*$#', '', $domain) ?? $domain;
        $domain = preg_replace('#:\d+$#', '', $domain) ?? $domain;
        $domain = mb_strtolower(trim($domain));

        if (! filter_var('http://'.$domain, FILTER_VALIDATE_URL) && ! preg_match('/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z0-9][a-z0-9-]{0,61}[a-z0-9]$/i', $domain)) {
            throw new InvalidArgumentException("اسم النطاق غير صالح: {$rawDomain}");
        }

        // Prevent attaching central/reserved domains
        $centralDomains = (array) config('tenancy.central_domains', ['127.0.0.1', 'localhost']);
        if (in_array($domain, $centralDomains, true)) {
            throw new InvalidArgumentException('لا يمكن استخدام هذا النطاق المركزي كنطاق مخصص.');
        }

        return $domain;
    }

    /**
     * Add a custom domain for a tenant with verification details.
     */
    public function addCustomDomain(Tenant $tenant, string $rawDomain): Domain
    {
        Features::ensure('custom_domain', $tenant);

        $normalized = $this->normalizeDomain($rawDomain);

        // Check uniqueness across all domains
        if (Domain::query()->where('domain', $normalized)->exists()) {
            throw new InvalidArgumentException("النطاق '{$normalized}' مستخدم بالفعل.");
        }

        $token = 'aff_verify_'.Str::random(32);
        $cnameTarget = (string) config('tenancy.cname_target', 'cname.affiliate-os.com');
        $serverIp = (string) config('tenancy.server_ip', '127.0.0.1');

        /** @var Domain $domain */
        $domain = Domain::create([
            'domain' => $normalized,
            'tenant_id' => (string) $tenant->id,
            'is_primary' => false,
            'is_custom' => true,
            'status' => Domain::STATUS_PENDING_VERIFICATION,
            'verification_token' => $token,
            'verification_method' => 'dns_txt',
            'ssl_status' => Domain::SSL_STATUS_PENDING,
            'dns_records' => [
                'txt' => [
                    'host' => "_affiliate-os-challenge.{$normalized}",
                    'value' => $token,
                    'type' => 'TXT',
                ],
                'cname' => [
                    'host' => $normalized,
                    'target' => $cnameTarget,
                    'type' => 'CNAME',
                ],
                'a' => [
                    'host' => $normalized,
                    'ip' => $serverIp,
                    'type' => 'A',
                ],
            ],
            'redirect_to_primary' => false,
        ]);

        return $domain;
    }

    /**
     * Verify DNS records for custom domain.
     */
    public function verifyDomain(Domain $domain): bool
    {
        if (! $domain->is_custom || empty($domain->verification_token)) {
            return false;
        }

        $hostname = $domain->domain;
        $challengeHost = "_affiliate-os-challenge.{$hostname}";
        $expectedToken = (string) $domain->verification_token;
        $expectedCname = (string) config('tenancy.cname_target', 'cname.affiliate-os.com');
        $expectedIp = (string) config('tenancy.server_ip', '127.0.0.1');

        $isVerified = false;

        // 1. Check TXT record at challenge host or domain root
        $txtRecords = array_merge(
            $this->dnsResolver->getTxtRecords($challengeHost),
            $this->dnsResolver->getTxtRecords($hostname)
        );

        foreach ($txtRecords as $txt) {
            if (trim($txt) === $expectedToken) {
                $isVerified = true;
                break;
            }
        }

        // 2. Check CNAME record
        if (! $isVerified) {
            $cname = $this->dnsResolver->getCnameRecord($hostname);
            if ($cname !== null && (strcasecmp($cname, $expectedCname) === 0 || str_ends_with(strtolower($cname), 'affiliate-os.com'))) {
                $isVerified = true;
            }
        }

        // 3. Check A record
        if (! $isVerified) {
            $aRecords = $this->dnsResolver->getARecords($hostname);
            if (in_array($expectedIp, $aRecords, true)) {
                $isVerified = true;
            }
        }

        $domain->last_verified_at = now();

        if ($isVerified) {
            $domain->status = Domain::STATUS_ACTIVE;
            $domain->verified_at = now();
            $domain->save();

            // Provision on Hostinger / Web Server
            $parkedOk = $this->hostingerClient->createParkedDomain($domain->domain);
            if ($parkedOk) {
                $sslOk = $this->hostingerClient->requestSsl($domain->domain);
                $domain->ssl_status = $sslOk ? Domain::SSL_STATUS_INSTALLING : Domain::SSL_STATUS_PENDING;
                $domain->ssl_provider = 'hostinger';
                $domain->save();
            }

            return true;
        }

        $domain->save();

        return false;
    }

    /**
     * Set a domain as the primary domain for the tenant.
     */
    public function setPrimaryDomain(Tenant $tenant, Domain $domain): void
    {
        if ((string) $domain->tenant_id !== (string) $tenant->id) {
            throw new InvalidArgumentException('الدومين المحدد لا ينتمي لهذا المستأجر.');
        }

        if ($domain->status !== Domain::STATUS_ACTIVE) {
            throw new InvalidArgumentException('لا يمكن تعيين دومين غير مفعل أو غير مؤكد كنطاق أساسي.');
        }

        // Unset primary on other domains
        Domain::query()
            ->where('tenant_id', (string) $tenant->id)
            ->update(['is_primary' => false]);

        $domain->is_primary = true;
        $domain->save();
    }

    /**
     * Configure redirection from default subdomain to primary custom domain.
     */
    public function setSubdomainRedirection(Tenant $tenant, bool $redirect): void
    {
        Domain::query()
            ->where('tenant_id', (string) $tenant->id)
            ->where('is_custom', false)
            ->update(['redirect_to_primary' => $redirect]);
    }

    /**
     * Remove custom domain.
     */
    public function removeCustomDomain(Tenant $tenant, Domain $domain): void
    {
        if ((string) $domain->tenant_id !== (string) $tenant->id) {
            throw new InvalidArgumentException('الدومين المحدد لا ينتمي لهذا المستأجر.');
        }

        if (! $domain->is_custom) {
            throw new InvalidArgumentException('لا يمكن حذف النطاق الفرعي الافتراضي للمتجر.');
        }

        $wasPrimary = $domain->is_primary;

        $this->hostingerClient->removeParkedDomain($domain->domain);
        $domain->delete();

        // If the deleted domain was primary, revert primary to the default subdomain
        if ($wasPrimary) {
            $defaultSubdomain = Domain::query()
                ->where('tenant_id', (string) $tenant->id)
                ->where('is_custom', false)
                ->first();

            if ($defaultSubdomain) {
                $defaultSubdomain->is_primary = true;
                $defaultSubdomain->redirect_to_primary = false;
                $defaultSubdomain->save();
            }
        }
    }
}
