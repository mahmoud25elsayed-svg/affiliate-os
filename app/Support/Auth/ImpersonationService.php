<?php

declare(strict_types=1);

namespace App\Support\Auth;

use App\Models\Tenant;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;

class ImpersonationService
{
    public const SESSION_IMPERSONATOR_ID = 'impersonator_id';

    public const SESSION_IMPERSONATOR_NAME = 'impersonator_name';

    public const SESSION_TENANT_ID = 'impersonated_tenant_id';

    public const SESSION_EXPIRES_AT = 'impersonation_expires_at';

    /**
     * Start impersonating a tenant as platform admin.
     */
    public function impersonate(User $platformAdmin, Tenant $tenant, int $durationMinutes = 60): User
    {
        if (! $platformAdmin->isPlatformAdmin()) {
            throw new InvalidArgumentException('Only platform administrators can impersonate tenants.');
        }

        /** @var User|null $targetUser */
        $targetUser = $tenant->owner()->first() ?? $tenant->users()->first();

        if (! $targetUser) {
            throw new InvalidArgumentException("Tenant [{$tenant->id}] has no users to impersonate.");
        }

        $expiresAt = now()->addMinutes($durationMinutes)->timestamp;

        session()->put([
            self::SESSION_IMPERSONATOR_ID => $platformAdmin->id,
            self::SESSION_IMPERSONATOR_NAME => $platformAdmin->name,
            self::SESSION_TENANT_ID => $tenant->id,
            self::SESSION_EXPIRES_AT => $expiresAt,
        ]);

        AuditLogger::log(
            action: 'tenant.impersonate',
            subject: $tenant,
            before: null,
            after: [
                'tenant_id' => $tenant->id,
                'tenant_name' => $tenant->name,
                'target_user_id' => $targetUser->id,
                'target_user_email' => $targetUser->email,
                'duration_minutes' => $durationMinutes,
                'expires_at' => $expiresAt,
            ],
            actor: $platformAdmin,
            tenantId: (string) $tenant->id
        );

        Auth::login($targetUser);

        return $targetUser;
    }

    /**
     * Stop impersonating and restore platform admin session.
     */
    public function stopImpersonating(): ?User
    {
        if (! session()->has(self::SESSION_IMPERSONATOR_ID)) {
            return null;
        }

        $impersonatorId = (int) session()->get(self::SESSION_IMPERSONATOR_ID);
        $tenantId = (string) session()->get(self::SESSION_TENANT_ID);

        /** @var User|null $platformAdmin */
        $platformAdmin = User::find($impersonatorId);

        AuditLogger::log(
            action: 'tenant.impersonate.ended',
            subject: $tenantId,
            before: null,
            after: [
                'tenant_id' => $tenantId,
                'ended_at' => now()->toIso8601String(),
            ],
            actor: $platformAdmin,
            tenantId: $tenantId
        );

        session()->forget([
            self::SESSION_IMPERSONATOR_ID,
            self::SESSION_IMPERSONATOR_NAME,
            self::SESSION_TENANT_ID,
            self::SESSION_EXPIRES_AT,
        ]);

        if ($platformAdmin) {
            Auth::login($platformAdmin);
        }

        return $platformAdmin;
    }

    /**
     * Check if current session is an active impersonation.
     */
    public function isImpersonating(): bool
    {
        if (! session()->has(self::SESSION_IMPERSONATOR_ID)) {
            return false;
        }

        $expiresAt = (int) session()->get(self::SESSION_EXPIRES_AT, 0);
        if (time() >= $expiresAt) {
            $this->stopImpersonating();

            return false;
        }

        return true;
    }

    /**
     * Get remaining impersonation seconds.
     */
    public function getRemainingSeconds(): int
    {
        if (! $this->isImpersonating()) {
            return 0;
        }

        $expiresAt = (int) session()->get(self::SESSION_EXPIRES_AT, 0);

        return max(0, $expiresAt - time());
    }
}
