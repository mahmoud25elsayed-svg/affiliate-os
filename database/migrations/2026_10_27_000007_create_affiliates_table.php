<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('affiliates')) {
            Schema::create('affiliates', function (Blueprint $table) {
                $table->id();
                $table->string('tenant_id')->index();
                $table->string('name');
                $table->string('code');
                $table->integer('commission_rate')->default(10);
                $table->string('status')->default('active');
                $table->timestamps();

                $table->unique(['tenant_id', 'code']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('affiliates');
    }
};
