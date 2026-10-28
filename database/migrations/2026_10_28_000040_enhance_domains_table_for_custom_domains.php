<?php

declare(strict_types=1);

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
        Schema::table('domains', function (Blueprint $table) {
            $table->boolean('is_primary')->default(false)->after('tenant_id');
            $table->boolean('is_custom')->default(false)->after('is_primary');
            $table->string('status', 32)->default('active')->after('is_custom');
            $table->string('verification_token', 64)->nullable()->after('status');
            $table->string('verification_method', 32)->default('dns_txt')->after('verification_token');
            $table->timestamp('verified_at')->nullable()->after('verification_method');
            $table->timestamp('last_verified_at')->nullable()->after('verified_at');
            $table->string('ssl_status', 32)->default('pending')->after('last_verified_at');
            $table->string('ssl_provider', 32)->nullable()->after('ssl_status');
            $table->timestamp('ssl_installed_at')->nullable()->after('ssl_provider');
            $table->json('dns_records')->nullable()->after('ssl_installed_at');
            $table->boolean('redirect_to_primary')->default(false)->after('dns_records');

            $table->index(['tenant_id', 'is_primary']);
            $table->index(['tenant_id', 'is_custom']);
            $table->index(['status', 'ssl_status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('domains', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'is_primary']);
            $table->dropIndex(['tenant_id', 'is_custom']);
            $table->dropIndex(['status', 'ssl_status']);

            $table->dropColumn([
                'is_primary',
                'is_custom',
                'status',
                'verification_token',
                'verification_method',
                'verified_at',
                'last_verified_at',
                'ssl_status',
                'ssl_provider',
                'ssl_installed_at',
                'dns_records',
                'redirect_to_primary',
            ]);
        });
    }
};
