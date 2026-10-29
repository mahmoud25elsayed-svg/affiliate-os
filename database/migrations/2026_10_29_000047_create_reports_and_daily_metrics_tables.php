<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_store_metrics', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id')->index();
            $table->date('metric_date')->index();
            $table->unsignedInteger('orders_count')->default(0);
            $table->unsignedInteger('confirmed_orders_count')->default(0);
            $table->unsignedInteger('delivered_orders_count')->default(0);
            $table->unsignedInteger('returned_orders_count')->default(0);
            $table->unsignedInteger('cancelled_orders_count')->default(0);
            $table->bigInteger('gross_revenue_minor')->default(0);
            $table->bigInteger('affiliate_commission_minor')->default(0);
            $table->bigInteger('shipping_expense_minor')->default(0);
            $table->bigInteger('cogs_minor')->default(0);
            $table->bigInteger('tax_minor')->default(0);
            $table->bigInteger('net_profit_minor')->default(0);
            $table->timestamps();

            $table->unique(['tenant_id', 'metric_date'], 'daily_store_metrics_tenant_date_unique');
        });

        Schema::create('tenant_error_logs', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id')->nullable()->index();
            $table->string('level')->default('error');
            $table->text('message');
            $table->json('context')->nullable();
            $table->string('exception_class')->nullable();
            $table->timestamp('created_at')->useCurrent()->index();

            $table->index(['tenant_id', 'created_at'], 'tenant_error_logs_tenant_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_store_metrics');
        Schema::dropIfExists('tenant_error_logs');
    }
};
