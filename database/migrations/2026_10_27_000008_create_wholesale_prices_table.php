<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('wholesale_prices')) {
            Schema::create('wholesale_prices', function (Blueprint $table) {
                $table->id();
                $table->string('tenant_id')->index();
                $table->unsignedBigInteger('product_variant_id')->index();
                $table->bigInteger('wholesale_price_minor');
                $table->timestamps();

                $table->unique(['tenant_id', 'product_variant_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('wholesale_prices');
    }
};
