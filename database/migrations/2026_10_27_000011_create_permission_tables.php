<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $columnName = config('permission.column_names.team_foreign_key', 'tenant_id');

        if (Schema::hasTable('roles') && ! Schema::hasColumn('roles', $columnName)) {
            Schema::table('roles', function (Blueprint $table) use ($columnName) {
                $table->string($columnName)->nullable()->index();
            });
        }

        if (Schema::hasTable('model_has_roles') && ! Schema::hasColumn('model_has_roles', $columnName)) {
            Schema::table('model_has_roles', function (Blueprint $table) use ($columnName) {
                $table->string($columnName)->nullable()->index();
            });
        }

        if (Schema::hasTable('model_has_permissions') && ! Schema::hasColumn('model_has_permissions', $columnName)) {
            Schema::table('model_has_permissions', function (Blueprint $table) use ($columnName) {
                $table->string($columnName)->nullable()->index();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $columnName = config('permission.column_names.team_foreign_key', 'tenant_id');

        if (Schema::hasTable('roles') && Schema::hasColumn('roles', $columnName)) {
            Schema::table('roles', function (Blueprint $table) use ($columnName) {
                $table->dropColumn($columnName);
            });
        }

        if (Schema::hasTable('model_has_roles') && Schema::hasColumn('model_has_roles', $columnName)) {
            Schema::table('model_has_roles', function (Blueprint $table) use ($columnName) {
                $table->dropColumn($columnName);
            });
        }

        if (Schema::hasTable('model_has_permissions') && Schema::hasColumn('model_has_permissions', $columnName)) {
            Schema::table('model_has_permissions', function (Blueprint $table) use ($columnName) {
                $table->dropColumn($columnName);
            });
        }
    }
};
