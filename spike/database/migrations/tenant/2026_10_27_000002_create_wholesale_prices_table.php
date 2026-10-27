<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wholesale_prices', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_variant_id')->index();
            $table->bigInteger('wholesale_price_minor'); // in piastres
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wholesale_prices');
    }
};
