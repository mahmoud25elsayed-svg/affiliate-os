<?php

declare(strict_types=1);

namespace App\Domain\Tenancy\Services;

use App\Domain\Tenancy\Contracts\HostingerClientInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class HostingerDomainService implements HostingerClientInterface
{
    protected string $apiToken;

    protected string $baseUrl;

    protected string $parentDomain;

    protected string $username;

    public function __construct()
    {
        $this->apiToken = (string) config('services.hostinger.api_token', '');
        $this->baseUrl = (string) config('services.hostinger.base_url', 'https://api.hostinger.com/v1');
        $this->parentDomain = (string) config('services.hostinger.parent_domain', 'affiliate-os.com');
        $this->username = (string) config('services.hostinger.username', 'u123456789');
    }

    public function isConfigured(): bool
    {
        return ! empty($this->apiToken);
    }

    public function createParkedDomain(string $customDomain): bool
    {
        if (! $this->isConfigured()) {
            Log::info("HostingerDomainService [Mock]: Parked domain registered: {$customDomain} -> {$this->parentDomain}");

            return true;
        }

        try {
            $response = Http::withToken($this->apiToken)
                ->post("{$this->baseUrl}/hosting/websites/{$this->parentDomain}/parked", [
                    'username' => $this->username,
                    'domain' => $this->parentDomain,
                    'parked_domain' => $customDomain,
                ]);

            if ($response->successful()) {
                Log::info("Hostinger API: Parked domain created for {$customDomain}");

                return true;
            }

            Log::error("Hostinger API error creating parked domain {$customDomain}: ".$response->body());

            return false;
        } catch (\Throwable $e) {
            Log::error("Hostinger API exception creating parked domain {$customDomain}: ".$e->getMessage());

            return false;
        }
    }

    public function requestSsl(string $customDomain): bool
    {
        if (! $this->isConfigured()) {
            Log::info("HostingerDomainService [Mock]: SSL install requested for {$customDomain}");

            return true;
        }

        try {
            $response = Http::withToken($this->apiToken)
                ->post("{$this->baseUrl}/hosting/websites/{$customDomain}/ssl", [
                    'username' => $this->username,
                    'domain' => $customDomain,
                ]);

            return $response->successful();
        } catch (\Throwable $e) {
            Log::error("Hostinger API exception requesting SSL for {$customDomain}: ".$e->getMessage());

            return false;
        }
    }

    public function getSslStatus(string $customDomain): array
    {
        if (! $this->isConfigured()) {
            return [
                'status' => 'active',
                'provider' => 'Let\'s Encrypt (Simulated)',
                'expires_at' => now()->addDays(90)->toIso8601String(),
                'error' => null,
            ];
        }

        try {
            $response = Http::withToken($this->apiToken)
                ->get("{$this->baseUrl}/hosting/websites/{$customDomain}/ssl");

            if ($response->successful()) {
                /** @var array{status?: string, provider?: string, expires_at?: string, last_error?: string} $data */
                $data = $response->json() ?? [];

                return [
                    'status' => $data['status'] ?? 'pending',
                    'provider' => $data['provider'] ?? null,
                    'expires_at' => $data['expires_at'] ?? null,
                    'error' => $data['last_error'] ?? null,
                ];
            }

            return [
                'status' => 'failed',
                'provider' => null,
                'expires_at' => null,
                'error' => $response->body(),
            ];
        } catch (\Throwable $e) {
            return [
                'status' => 'failed',
                'provider' => null,
                'expires_at' => null,
                'error' => $e->getMessage(),
            ];
        }
    }

    public function removeParkedDomain(string $customDomain): bool
    {
        if (! $this->isConfigured()) {
            Log::info("HostingerDomainService [Mock]: Parked domain removed: {$customDomain}");

            return true;
        }

        try {
            $response = Http::withToken($this->apiToken)
                ->delete("{$this->baseUrl}/hosting/websites/{$this->parentDomain}/parked/{$customDomain}");

            return $response->successful();
        } catch (\Throwable $e) {
            Log::error("Hostinger API exception removing parked domain {$customDomain}: ".$e->getMessage());

            return false;
        }
    }
}
