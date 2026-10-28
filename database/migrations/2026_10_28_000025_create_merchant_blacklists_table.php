<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('merchant_blacklists', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->string('type', 32); // 'phone', 'name', 'ip'
            $table->string('value', 255);
            $table->string('reason', 500)->nullable();
            $table->foreignId('added_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->unique(['tenant_id', 'type', 'value'], 'merchant_blacklist_unique');
            $table->index(['tenant_id', 'type'], 'merchant_blacklist_type_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('merchant_blacklists');
    }
};
