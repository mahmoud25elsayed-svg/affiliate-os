<?php

namespace App\Support\Tenancy\Permissions;

use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Contracts\PermissionsTeamResolver;

class TenantTeamResolver implements PermissionsTeamResolver
{
    protected int|string|null $teamId = null;

    /**
     * Set the team id for teams/groups support.
     *
     * @param  int|string|Model|null  $id
     */
    public function setPermissionsTeamId($id): void
    {
        if ($id instanceof Model) {
            $id = $id->getKey();
        }

        $this->teamId = $id;
    }

    /**
     * Get current team id. If tenancy is initialized, returns active tenant id.
     */
    public function getPermissionsTeamId(): int|string|null
    {
        if (tenancy()->initialized) {
            /** @var string|null $tenantId */
            $tenantId = tenant('id');

            return $tenantId;
        }

        return $this->teamId;
    }
}
