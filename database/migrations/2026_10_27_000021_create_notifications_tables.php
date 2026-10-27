<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_channels', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id', 36)->index();
            $table->string('channel', 32); // sms, whatsapp, email, in_app, web_push
            $table->string('provider', 64); // sms_misr, victorylink, meta_whatsapp, system_mail, in_app, fake
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->text('credentials')->nullable(); // Encrypted JSON payload
            $table->string('sender_id', 64)->nullable();
            $table->unsignedBigInteger('daily_cost_limit_minor')->default(50000); // 500 EGP in minor units
            $table->unsignedBigInteger('current_day_cost_minor')->default(0);
            $table->date('last_cost_reset_at')->nullable();
            $table->json('settings')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->unique(['tenant_id', 'channel', 'provider'], 'tenant_channel_provider_unique');
        });

        Schema::create('notification_templates', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id', 36)->index();
            $table->string('event_type', 64); // order_confirmed, order_shipped, order_delivered, order_cancelled, cart_abandoned, otp_verification, welcome
            $table->string('channel', 32); // sms, whatsapp, email, in_app
            $table->string('locale', 5)->default('ar');
            $table->string('title')->nullable();
            $table->string('subject')->nullable();
            $table->text('content');
            $table->json('variables')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->unique(['tenant_id', 'event_type', 'channel', 'locale'], 'tenant_event_channel_locale_unique');
        });

        Schema::create('notification_logs', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id', 36)->index();
            $table->string('channel', 32);
            $table->string('provider', 64);
            $table->string('recipient', 191);
            $table->string('event_type', 64);
            $table->string('subject')->nullable();
            $table->text('content');
            $table->string('status', 32)->default('pending'); // pending, sent, delivered, failed
            $table->text('error_message')->nullable();
            $table->string('external_id', 191)->nullable();
            $table->unsignedInteger('cost_minor')->default(0);
            $table->unsignedTinyInteger('retry_count')->default(0);
            $table->timestamp('sent_at')->nullable();
            $table->string('notifiable_type')->nullable();
            $table->unsignedBigInteger('notifiable_id')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'channel']);
            $table->index(['tenant_id', 'created_at']);
        });

        Schema::create('in_app_notifications', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id', 36)->index();
            $table->string('user_type', 32); // merchant, customer, affiliate
            $table->unsignedBigInteger('user_id')->index();
            $table->string('title');
            $table->text('body');
            $table->string('action_url')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->index(['tenant_id', 'user_type', 'user_id', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('in_app_notifications');
        Schema::dropIfExists('notification_logs');
        Schema::dropIfExists('notification_templates');
        Schema::dropIfExists('notification_channels');
    }
};
