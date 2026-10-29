<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Tickets table
        if (! Schema::hasTable('tickets')) {
            Schema::create('tickets', function (Blueprint $table) {
                $table->id();
                $table->string('tenant_id')->nullable()->index();
                $table->string('ticket_number', 50)->unique();
                $table->string('level', 20)->default('store')->index(); // store (customer/affiliate -> merchant), platform (merchant -> platform)
                $table->string('subject', 255);
                $table->string('priority', 20)->default('medium')->index(); // low, medium, high, urgent
                $table->string('status', 20)->default('open')->index(); // open, pending_customer, pending_agent, resolved, closed
                $table->string('department', 50)->default('general')->index(); // orders, finance, shipping, technical, general
                $table->unsignedBigInteger('created_by_user_id')->nullable()->index();
                $table->unsignedBigInteger('assigned_to_user_id')->nullable()->index();
                $table->unsignedBigInteger('customer_id')->nullable()->index();
                $table->unsignedBigInteger('affiliate_id')->nullable()->index();
                $table->unsignedBigInteger('order_id')->nullable()->index();
                $table->unsignedBigInteger('payout_request_id')->nullable()->index();
                $table->timestamp('sla_due_at')->nullable()->index();
                $table->timestamp('first_response_at')->nullable();
                $table->timestamp('resolved_at')->nullable();
                $table->timestamp('closed_at')->nullable();
                $table->timestamps();

                $table->index(['tenant_id', 'status']);
                $table->index(['tenant_id', 'level']);
                $table->index(['tenant_id', 'priority', 'status']);
            });
        }

        // 2. Ticket Messages & Internal Notes
        if (! Schema::hasTable('ticket_messages')) {
            Schema::create('ticket_messages', function (Blueprint $table) {
                $table->id();
                $table->string('tenant_id')->nullable()->index();
                $table->foreignId('ticket_id')->constrained('tickets')->cascadeOnDelete();
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->unsignedBigInteger('affiliate_id')->nullable()->index();
                $table->unsignedBigInteger('customer_id')->nullable()->index();
                $table->string('sender_type', 20)->default('customer'); // staff, customer, affiliate, platform_admin, system
                $table->text('message');
                $table->boolean('is_internal_note')->default(false)->index();
                $table->json('attachments')->nullable();
                $table->timestamps();

                $table->index(['ticket_id', 'created_at']);
            });
        }

        // 3. Ticket Canned Responses
        if (! Schema::hasTable('ticket_canned_responses')) {
            Schema::create('ticket_canned_responses', function (Blueprint $table) {
                $table->id();
                $table->string('tenant_id')->nullable()->index();
                $table->string('title', 150);
                $table->string('shortcut', 50)->index(); // e.g. /delay, /refund
                $table->text('content');
                $table->string('department', 50)->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index(['tenant_id', 'shortcut']);
            });
        }

        // 4. Help Center / FAQ Articles
        if (! Schema::hasTable('help_articles')) {
            Schema::create('help_articles', function (Blueprint $table) {
                $table->id();
                $table->string('tenant_id')->nullable()->index();
                $table->string('category', 60)->index();
                $table->string('audience', 20)->default('public')->index(); // public (customers), affiliates, merchants
                $table->string('question', 255);
                $table->string('slug', 255)->nullable()->index();
                $table->text('answer');
                $table->integer('sort_order')->default(0);
                $table->boolean('is_published')->default(true)->index();
                $table->timestamps();

                $table->index(['tenant_id', 'audience', 'category']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('help_articles');
        Schema::dropIfExists('ticket_canned_responses');
        Schema::dropIfExists('ticket_messages');
        Schema::dropIfExists('tickets');
    }
};
