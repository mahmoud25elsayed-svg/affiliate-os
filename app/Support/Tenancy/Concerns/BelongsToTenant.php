<?php

declare(strict_types=1);

namespace App\Support\Tenancy\Concerns;

use App\Models\Tenant;
use App\Support\Tenancy\Exceptions\TenantCannotBeNullException;
use App\Support\Tenancy\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $tenant_id
 * @property-read Tenant|null $tenant
 */
trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope);

        static::saving(function (Model $model): void {
            if (! $model->getAttribute('tenant_id') && function_exists('tenancy') && tenancy()->initialized) {
                $model->setAttribute('tenant_id', tenant('id'));
            }

            if (empty($model->getAttribute('tenant_id'))) {
                throw TenantCannotBeNullException::forModel($model::class);
            }
        });
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        /** @var class-string<Tenant> $tenantModel */
        $tenantModel = config('tenancy.tenant_model', Tenant::class);

        return $this->belongsTo($tenantModel, 'tenant_id');
    }
}
