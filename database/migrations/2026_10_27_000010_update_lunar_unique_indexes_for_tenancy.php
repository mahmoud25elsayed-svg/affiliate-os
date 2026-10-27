<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Currencies: drop unique on code, add unique on [tenant_id, code]
        if (Schema::hasTable('lunar_currencies')) {
            Schema::table('lunar_currencies', function (Blueprint $table) {
                try {
                    $table->dropUnique('lunar_currencies_code_unique');
                } catch (Throwable) {
                    // index might not exist or already dropped
                }
                $table->unique(['tenant_id', 'code']);
            });
        }

        // 2. Channels: drop unique on handle, add unique on [tenant_id, handle]
        if (Schema::hasTable('lunar_channels')) {
            Schema::table('lunar_channels', function (Blueprint $table) {
                try {
                    $table->dropUnique('lunar_channels_handle_unique');
                } catch (Throwable) {
                }
                $table->unique(['tenant_id', 'handle']);
            });
        }

        // 3. Customer Groups: drop unique on handle, add unique on [tenant_id, handle]
        if (Schema::hasTable('lunar_customer_groups')) {
            Schema::table('lunar_customer_groups', function (Blueprint $table) {
                try {
                    $table->dropUnique('lunar_customer_groups_handle_unique');
                } catch (Throwable) {
                }
                $table->unique(['tenant_id', 'handle']);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('lunar_currencies')) {
            Schema::table('lunar_currencies', function (Blueprint $table) {
                try {
                    $table->dropUnique(['tenant_id', 'code']);
                    $table->unique('code');
                } catch (Throwable) {
                }
            });
        }

        if (Schema::hasTable('lunar_channels')) {
            Schema::table('lunar_channels', function (Blueprint $table) {
                try {
                    $table->dropUnique(['tenant_id', 'handle']);
                    $table->unique('handle');
                } catch (Throwable) {
                }
            });
        }

        if (Schema::hasTable('lunar_customer_groups')) {
            Schema::table('lunar_customer_groups', function (Blueprint $table) {
                try {
                    $table->dropUnique(['tenant_id', 'handle']);
                    $table->unique('handle');
                } catch (Throwable) {
                }
            });
        }
    }
};
