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
        Schema::create('mobile_devices', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('affiliate_id')->nullable()->constrained('affiliates')->nullOnDelete();
            $table->string('device_token', 512);
            $table->string('platform', 32); // 'ios', 'android'
            $table->string('app_type', 32); // 'customer', 'affiliate', 'merchant'
            $table->string('device_name')->nullable();
            $table->string('app_version', 32)->nullable();
            $table->string('locale', 10)->default('ar');
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_active_at')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->unique(['tenant_id', 'device_token'], 'mobile_devices_tenant_token_unique');
            $table->index(['tenant_id', 'app_type', 'is_active']);
            $table->index(['tenant_id', 'affiliate_id']);
            $table->index(['tenant_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mobile_devices');
    }
};
