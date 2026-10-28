<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('call_attempts', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id', 36)->index();
            $table->foreignId('order_id')->constrained('lunar_orders')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedSmallInteger('attempt_number')->default(1);
            $table->string('outcome', 40); // confirmed, no_answer, busy, unreachable, customer_cancelled, postponed, wrong_number
            $table->text('notes')->nullable();
            $table->timestamp('scheduled_callback_at')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->string('recording_url')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'order_id']);
            $table->index(['tenant_id', 'user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('call_attempts');
    }
};
