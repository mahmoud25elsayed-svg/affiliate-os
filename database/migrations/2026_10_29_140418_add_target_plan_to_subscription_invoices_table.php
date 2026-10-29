<?php

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
        Schema::table('subscription_invoices', function (Blueprint $table) {
            $table->foreignId('target_plan_id')->nullable()->after('subscription_id')->constrained('plans')->nullOnDelete();
            $table->string('target_cycle', 20)->nullable()->after('target_plan_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subscription_invoices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('target_plan_id');
            $table->dropColumn('target_cycle');
        });
    }
};
