<?php

declare(strict_types=1);

namespace App\Support\Audit;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class AuditLogger
{
    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     */
    public static function log(
        string $action,
        Model|string $subject,
        ?array $before = null,
        ?array $after = null,
        ?Model $actor = null,
        ?string $tenantId = null
    ): AuditLog {
        $subjectType = is_string($subject) ? $subject : $subject->getMorphClass();
        $subjectId = is_string($subject) ? '0' : (string) $subject->getKey();

        $resolvedActor = $actor ?? Auth::user();
        $actorType = $resolvedActor ? $resolvedActor->getMorphClass() : 'system';
        $actorId = $resolvedActor ? (string) $resolvedActor->getKey() : null;

        $resolvedTenantId = $tenantId ?? (function_exists('tenant') && tenant() ? (string) tenant('id') : null);

        return AuditLog::create([
            'actor_type' => $actorType,
            'actor_id' => $actorId,
            'action' => $action,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'before' => $before,
            'after' => $after,
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
            'tenant_id' => $resolvedTenantId,
        ]);
    }
}
