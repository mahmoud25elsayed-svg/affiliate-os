<?php

declare(strict_types=1);

namespace App\Domain\Compliance\Services;

use App\Models\ConsentLog;
use Illuminate\Database\Eloquent\Collection;

class ConsentService
{
    /**
     * Record explicit legal consent from a merchant, affiliate, or customer.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function recordConsent(
        string $tenantId,
        string $subjectType,
        string $subjectIdentifier,
        string $documentType,
        string $version = '1.0',
        ?int $userId = null,
        ?string $ipAddress = null,
        ?string $userAgent = null,
        array $metadata = []
    ): ConsentLog {
        return ConsentLog::create([
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'subject_type' => $subjectType,
            'subject_identifier' => $subjectIdentifier,
            'document_type' => $documentType,
            'version' => $version,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'metadata' => $metadata,
            'accepted_at' => now(),
        ]);
    }

    /**
     * Verify if a subject has accepted a specific document version.
     */
    public function hasAccepted(
        string $tenantId,
        string $subjectType,
        string $subjectIdentifier,
        string $documentType,
        string $version = '1.0'
    ): bool {
        return ConsentLog::query()
            ->where('tenant_id', $tenantId)
            ->where('subject_type', $subjectType)
            ->where('subject_identifier', $subjectIdentifier)
            ->where('document_type', $documentType)
            ->where('version', $version)
            ->exists();
    }

    /**
     * Retrieve all consent logs for a given subject.
     *
     * @return Collection<int, ConsentLog>
     */
    public function getConsentHistory(string $tenantId, string $subjectIdentifier): Collection
    {
        return ConsentLog::query()
            ->where('tenant_id', $tenantId)
            ->where('subject_identifier', $subjectIdentifier)
            ->latest('accepted_at')
            ->get();
    }
}
