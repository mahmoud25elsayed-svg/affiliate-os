<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ai_usage_logs')) {
            Schema::create('ai_usage_logs', function (Blueprint $table) {
                $table->id();
                $table->string('tenant_id')->index();
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->unsignedBigInteger('affiliate_id')->nullable()->index();
                $table->string('provider', 30)->default('claude')->index();
                $table->string('model', 60)->index();
                $table->string('action', 50)->index(); // product_copy, seo_meta, category_tags, translate, ad_copy, marketing_angles, policy_check
                $table->unsignedInteger('prompt_tokens')->default(0);
                $table->unsignedInteger('completion_tokens')->default(0);
                $table->unsignedInteger('total_tokens')->default(0);
                $table->unsignedInteger('estimated_cost_cents')->default(0); // in integer minor cents
                $table->string('status', 20)->default('success')->index(); // success, failed
                $table->text('error_message')->nullable();
                $table->timestamps();

                $table->index(['tenant_id', 'created_at']);
                $table->index(['tenant_id', 'status', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_usage_logs');
    }
};
