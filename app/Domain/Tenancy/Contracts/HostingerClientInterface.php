<?php

declare(strict_types=1);

namespace App\Domain\Tenancy\Contracts;

interface HostingerClientInterface
{
    /**
     * Create a parked (alias) domain pointing to the central website.
     */
    public function createParkedDomain(string $customDomain): bool;

    /**
     * Request a lifetime SSL certificate for the parked custom domain.
     */
    public function requestSsl(string $customDomain): bool;

    /**
     * Retrieve current SSL installation status for the custom domain.
     *
     * @return array{status: string, provider: ?string, expires_at: ?string, error: ?string}
     */
    public function getSslStatus(string $customDomain): array;

    /**
     * Remove the parked domain.
     */
    public function removeParkedDomain(string $customDomain): bool;
}
