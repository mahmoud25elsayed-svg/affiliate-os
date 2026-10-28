<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('affiliate_levels', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id', 36)->index();
            $table->string('name_ar');
            $table->string('name_en');
            $table->string('slug');
            $table->unsignedTinyInteger('tier_order')->default(1);
            $table->string('badge_color', 20)->default('#94a3b8');
            $table->unsignedInteger('min_delivered_orders')->default(0);
            $table->unsignedSmallInteger('min_delivery_rate')->default(0); // basis points, e.g. 7000 = 70.00%
            $table->unsignedSmallInteger('min_quality_score')->default(0); // 0-100
            $table->unsignedInteger('bonus_per_order_minor')->default(0); // minor piastres per delivered order
            $table->unsignedTinyInteger('commission_bonus_percent')->default(0);
            $table->unsignedInteger('daily_order_limit')->default(50);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'slug']);
            $table->index(['tenant_id', 'tier_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('affiliate_levels');
    }
};
