<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('courier_rules', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id')->index();
            $table->foreignId('courier_account_id')->constrained('courier_accounts')->cascadeOnDelete();
            $table->foreignId('governorate_id')->nullable()->constrained('governorates')->cascadeOnDelete();
            $table->integer('priority')->default(0); // Higher number = higher priority
            $table->decimal('max_weight_kg', 8, 2)->nullable();
            $table->unsignedInteger('cod_limit_minor')->nullable(); // Max allowed COD amount
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['tenant_id', 'governorate_id', 'priority']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('courier_rules');
    }
};
