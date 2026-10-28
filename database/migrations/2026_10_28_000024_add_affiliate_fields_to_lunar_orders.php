<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lunar_orders', function (Blueprint $table) {
            if (! Schema::hasColumn('lunar_orders', 'affiliate_id')) {
                $table->foreignId('affiliate_id')
                    ->nullable()
                    ->after('storefront_customer_id')
                    ->constrained('affiliates')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('lunar_orders', 'affiliate_profit_minor')) {
                $table->integer('affiliate_profit_minor')
                    ->default(0)
                    ->after('affiliate_id');
            }

            if (! Schema::hasColumn('lunar_orders', 'quality_gate_reviewed_at')) {
                $table->timestamp('quality_gate_reviewed_at')
                    ->nullable()
                    ->after('quality_flags');
            }

            if (! Schema::hasColumn('lunar_orders', 'quality_gate_reviewed_by')) {
                $table->foreignId('quality_gate_reviewed_by')
                    ->nullable()
                    ->after('quality_gate_reviewed_at')
                    ->constrained('users')
                    ->nullOnDelete();
            }

            $table->index(['tenant_id', 'affiliate_id', 'status', 'created_at'], 'orders_affiliate_status_idx');
        });
    }

    public function down(): void
    {
        Schema::table('lunar_orders', function (Blueprint $table) {
            $table->dropIndex('orders_affiliate_status_idx');
            $table->dropConstrainedForeignId('quality_gate_reviewed_by');
            $table->dropColumn('quality_gate_reviewed_at');
            $table->dropColumn('affiliate_profit_minor');
            $table->dropConstrainedForeignId('affiliate_id');
        });
    }
};
