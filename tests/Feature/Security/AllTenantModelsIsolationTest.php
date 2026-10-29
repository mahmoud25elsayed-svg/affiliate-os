<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Models\CustomerReputation;
use App\Models\Domain;
use App\Models\Plan;
use App\Models\PlatformVisit;
use App\Models\Subscription;
use App\Models\SubscriptionEvent;
use App\Models\SubscriptionInvoice;
use App\Models\Tenant;
use App\Models\TenantErrorLog;
use App\Models\User;
use App\Support\Tenancy\Concerns\BelongsToTenant;
use App\Support\Tenancy\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AllTenantModelsIsolationTest extends TestCase
{
    /**
     * List of central / platform models that are not scoped to a single tenant.
     *
     * @var list<class-string<Model>>
     */
    protected array $platformModels = [
        Tenant::class,
        Domain::class,
        User::class,
        Plan::class,
        Subscription::class,
        SubscriptionEvent::class,
        SubscriptionInvoice::class,
        CustomerReputation::class,
        TenantErrorLog::class,
        PlatformVisit::class,
    ];

    /**
     * Verify that every tenant model uses BelongsToTenant trait and has TenantScope registered.
     */
    public function test_every_tenant_model_uses_belongs_to_tenant_and_has_tenant_scope(): void
    {
        $tenantModels = $this->getTenantModelClasses();

        $this->assertNotEmpty($tenantModels, 'Expected to find tenant models in app/Models/');

        foreach ($tenantModels as $modelClass) {
            $traits = class_uses_recursive($modelClass);

            $this->assertArrayHasKey(
                BelongsToTenant::class,
                $traits,
                "Model [{$modelClass}] must use BelongsToTenant trait."
            );

            /** @var Model $instance */
            $instance = new $modelClass;
            $globalScopes = $instance->getGlobalScopes();

            $hasTenantScope = false;
            foreach ($globalScopes as $scope) {
                if ($scope instanceof TenantScope) {
                    $hasTenantScope = true;
                    break;
                }
            }

            $this->assertTrue(
                $hasTenantScope,
                "Model [{$modelClass}] must have TenantScope registered in global scopes."
            );
        }
    }

    /**
     * Verify strict runtime tenant isolation across every tenant model:
     * Records created under Tenant B must never be visible, searchable, or accessible
     * under Tenant A's session.
     */
    public function test_runtime_isolation_across_all_tenant_models(): void
    {
        $tenantA = $this->ensureTenantExists('tenant_iso_alpha');
        $tenantB = $this->ensureTenantExists('tenant_iso_beta');

        $tenantModels = $this->getTenantModelClasses();

        foreach ($tenantModels as $modelClass) {
            /** @var Model $instance */
            $instance = new $modelClass;
            $table = $instance->getTable();

            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'tenant_id')) {
                continue;
            }

            // Insert a test record belonging to Tenant B
            $recordBId = $this->insertDummyRecordForTenant($table, (string) $tenantB->id);
            if ($recordBId === null) {
                continue;
            }

            // 1. Activate Tenant A session
            tenancy()->initialize($tenantA);

            // Assert Tenant A cannot find Tenant B's record by find()
            $foundByFind = $modelClass::find($recordBId);
            $this->assertNull(
                $foundByFind,
                "Tenant A was able to find Tenant B's record via find() in model [{$modelClass}]."
            );

            // Assert Tenant A cannot find Tenant B's record by where()
            $foundByWhere = $modelClass::where('id', $recordBId)->first();
            $this->assertNull(
                $foundByWhere,
                "Tenant A was able to find Tenant B's record via where() in model [{$modelClass}]."
            );

            // Assert Tenant A collection does not contain Tenant B's record
            $allIdsForTenantA = $modelClass::all()->pluck('id')->all();
            $this->assertNotContains(
                $recordBId,
                $allIdsForTenantA,
                "Tenant A collection contained Tenant B's record in model [{$modelClass}]."
            );

            // 2. Activate Tenant B session
            tenancy()->initialize($tenantB);

            // Assert Tenant B CAN find its own record
            $foundForTenantB = $modelClass::find($recordBId);
            $this->assertNotNull(
                $foundForTenantB,
                "Tenant B should be able to access its own record in model [{$modelClass}]."
            );
            $this->assertEquals((string) $tenantB->id, (string) $foundForTenantB->tenant_id);

            // 3. Verify bypass macro works for central administration
            $unscopedRecord = $modelClass::withoutTenancy()->find($recordBId);
            $this->assertNotNull(
                $unscopedRecord,
                "Model [{$modelClass}] withoutTenancy macro should allow unscoped access."
            );

            // Clean up
            tenancy()->end();
            DB::table($table)->where('id', $recordBId)->delete();
        }
    }

    /**
     * Get all tenant model class names.
     *
     * @return list<class-string<Model>>
     */
    protected function getTenantModelClasses(): array
    {
        $files = glob(app_path('Models/*.php')) ?: [];
        $classes = [];

        foreach ($files as $file) {
            /** @var class-string<Model> $className */
            $className = 'App\\Models\\'.basename($file, '.php');

            if (! class_exists($className)) {
                continue;
            }

            if (in_array($className, $this->platformModels, true)) {
                continue;
            }

            $classes[] = $className;
        }

        return $classes;
    }

    /**
     * Insert a minimal valid dummy row for a given table with tenant_id.
     */
    protected function insertDummyRecordForTenant(string $table, string $tenantId): ?int
    {
        try {
            $columns = DB::select("PRAGMA table_info('{$table}')");
        } catch (\Throwable) {
            return null;
        }

        $row = [];
        $uniqueSuffix = bin2hex(random_bytes(4));

        foreach ($columns as $col) {
            $name = (string) $col->name;
            $type = strtolower((string) $col->type);
            $notnull = (int) $col->notnull;
            $dflt = $col->dflt_value;

            // Auto-increment primary key
            if ($name === 'id' || (int) $col->pk === 1) {
                continue;
            }

            if ($name === 'tenant_id') {
                $row[$name] = $tenantId;
                continue;
            }

            // If column allows null or has default, we can omit it unless required
            if ($notnull === 0 || $dflt !== null) {
                continue;
            }

            // Required columns need valid dummy data
            if (str_contains($name, 'sku') || str_contains($name, 'code') || str_contains($name, 'reference') || str_contains($name, 'key') || str_contains($name, 'token') || str_contains($name, 'slug') || str_contains($name, 'hash')) {
                $row[$name] = "test_{$uniqueSuffix}_".substr($name, 0, 8);
            } elseif (str_contains($name, 'email')) {
                $row[$name] = "iso_{$uniqueSuffix}@example.com";
            } elseif (str_contains($name, 'phone')) {
                $row[$name] = '010'.rand(10000000, 99999999);
            } elseif (str_contains($name, 'url')) {
                $row[$name] = "https://example.com/test_{$uniqueSuffix}";
            } elseif (str_contains($name, 'status')) {
                $row[$name] = 'active';
            } elseif (str_contains($name, 'type') || str_contains($name, 'driver') || str_contains($name, 'method') || str_contains($name, 'channel') || str_contains($name, 'level') || str_contains($name, 'reason')) {
                $row[$name] = 'general';
            } elseif (str_contains($name, 'date') || str_contains($name, 'at')) {
                $row[$name] = now()->toDateTimeString();
            } elseif (str_contains($name, 'events') || str_contains($name, 'scopes') || str_contains($name, 'tags') || str_contains($name, 'details') || str_contains($name, 'data') || str_contains($name, 'settings') || str_contains($name, 'metadata') || str_contains($name, 'content') || str_contains($name, 'items') || str_contains($name, 'payload') || str_contains($name, 'context') || str_contains($name, 'metrics')) {
                $row[$name] = '{}';
            } elseif (str_contains($type, 'int') || str_contains($type, 'tinyint') || str_contains($type, 'decimal') || str_contains($type, 'float') || str_contains($type, 'numeric')) {
                $row[$name] = 1;
            } elseif (str_contains($type, 'bool')) {
                $row[$name] = 1;
            } else {
                $row[$name] = 'test_val';
            }
        }

        try {
            return (int) DB::table($table)->insertGetId($row);
        } catch (\Throwable) {
            return null;
        }
    }
}
