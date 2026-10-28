<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('affiliates', function (Blueprint $table) {
            $table->foreignId('affiliate_level_id')->nullable()->constrained('affiliate_levels')->nullOnDelete();
            $table->unsignedSmallInteger('quality_score')->default(100);
            $table->unsignedInteger('daily_order_limit')->default(50);
            $table->unsignedInteger('total_delivered_orders')->default(0);
            $table->unsignedSmallInteger('delivery_rate_basis_points')->default(10000); // 100.00%
        });
    }

    public function down(): void
    {
        Schema::table('affiliates', function (Blueprint $table) {
            $table->dropConstrainedForeignId('affiliate_level_id');
            $table->dropColumn([
                'quality_score',
                'daily_order_limit',
                'total_delivered_orders',
                'delivery_rate_basis_points',
            ]);
        });
    }
};
