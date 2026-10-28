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
        Schema::create('tracking_links', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->foreignId('affiliate_id')->constrained('affiliates')->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('lunar_products')->nullOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained('lunar_product_variants')->nullOnDelete();
            $table->string('code', 64);
            $table->string('title')->nullable();
            $table->unsignedInteger('custom_selling_price_minor')->nullable();
            $table->decimal('commission_rate_percent', 5, 2)->nullable();
            $table->unsignedInteger('clicks_count')->default(0);
            $table->unsignedInteger('orders_count')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'code']);
            $table->index(['tenant_id', 'affiliate_id']);
            $table->index(['tenant_id', 'product_id']);
        });

        Schema::create('affiliate_clicks', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->foreignId('tracking_link_id')->constrained('tracking_links')->cascadeOnDelete();
            $table->foreignId('affiliate_id')->constrained('affiliates')->cascadeOnDelete();
            $table->string('ip_hash', 64);
            $table->string('ua_hash', 64)->nullable();
            $table->string('referer', 500)->nullable();
            $table->string('utm_source', 100)->nullable();
            $table->string('utm_medium', 100)->nullable();
            $table->string('utm_campaign', 100)->nullable();
            $table->string('utm_term', 100)->nullable();
            $table->string('utm_content', 100)->nullable();
            $table->string('landing_url', 1000)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['tenant_id', 'tracking_link_id', 'created_at']);
            $table->index(['tenant_id', 'ip_hash', 'created_at']);
        });

        Schema::table('lunar_orders', function (Blueprint $table) {
            $table->foreignId('tracking_link_id')->nullable()->after('affiliate_id')->constrained('tracking_links')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lunar_orders', function (Blueprint $table) {
            $table->dropForeign(['tracking_link_id']);
            $table->dropColumn('tracking_link_id');
        });

        Schema::dropIfExists('affiliate_clicks');
        Schema::dropIfExists('tracking_links');
    }
};
