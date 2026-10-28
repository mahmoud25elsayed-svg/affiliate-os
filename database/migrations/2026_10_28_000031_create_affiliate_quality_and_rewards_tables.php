<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('affiliate_quality_logs', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id', 36)->index();
            $table->foreignId('affiliate_id')->constrained('affiliates')->cascadeOnDelete();
            $table->date('calculated_date');
            $table->unsignedSmallInteger('quality_score');
            $table->unsignedInteger('delivered_count')->default(0);
            $table->unsignedInteger('returned_count')->default(0);
            $table->unsignedInteger('rejected_count')->default(0);
            $table->unsignedSmallInteger('delivery_rate')->default(10000);
            $table->unsignedInteger('daily_order_limit')->default(50);
            $table->foreignId('affiliate_level_id')->nullable()->constrained('affiliate_levels')->nullOnDelete();
            $table->json('reasons')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'affiliate_id', 'calculated_date'], 'aff_quality_logs_tenant_aff_date_unique');
        });

        Schema::create('affiliate_referral_rewards', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id', 36)->index();
            $table->foreignId('referrer_id')->constrained('affiliates')->cascadeOnDelete();
            $table->foreignId('referred_id')->constrained('affiliates')->cascadeOnDelete();
            $table->unsignedInteger('threshold_orders');
            $table->unsignedInteger('reward_amount_minor');
            $table->unsignedBigInteger('journal_entry_id')->nullable();
            $table->timestamp('awarded_at');
            $table->timestamps();

            $table->unique(
                ['tenant_id', 'referrer_id', 'referred_id', 'threshold_orders'],
                'aff_ref_rewards_tenant_pair_threshold_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('affiliate_referral_rewards');
        Schema::dropIfExists('affiliate_quality_logs');
    }
};
