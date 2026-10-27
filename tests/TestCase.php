<?php

namespace Tests;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function ensureTenantExists(string $id): Tenant
    {
        $tenant = Tenant::find($id);
        if ($tenant) {
            return $tenant;
        }

        $dbPath = database_path("tenant_{$id}.sqlite");
        if (file_exists($dbPath)) {
            @unlink($dbPath);
        }

        return Tenant::create(['id' => $id]);
    }
}
