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
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('locked_until')->nullable();
            $table->unsignedSmallInteger('call_attempts_count')->default(0);
            $table->timestamp('next_attempt_at')->nullable();
            $table->unsignedInteger('confirmation_priority')->default(0);

            $table->index(['tenant_id', 'status', 'locked_until', 'next_attempt_at'], 'orders_cc_queue_idx');
            $table->index(['tenant_id', 'assigned_to'], 'orders_assigned_idx');
        });
    }

    public function down(): void
    {
        Schema::table('lunar_orders', function (Blueprint $table) {
            $table->dropIndex('orders_cc_queue_idx');
            $table->dropIndex('orders_assigned_idx');
            $table->dropConstrainedForeignId('assigned_to');
            $table->dropColumn([
                'locked_until',
                'call_attempts_count',
                'next_attempt_at',
                'confirmation_priority',
            ]);
        });
    }
};
