<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipments', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id')->index();
            $table->foreignId('order_id')->constrained('lunar_orders')->cascadeOnDelete();
            $table->foreignId('courier_account_id')->constrained('courier_accounts')->cascadeOnDelete();
            $table->string('tracking_number')->index();
            $table->string('waybill_url')->nullable();
            $table->string('status')->default('created')->index(); // ShipmentStatus enum
            $table->unsignedInteger('cod_amount_minor')->default(0);
            $table->unsignedInteger('shipping_fees_minor')->default(0);
            $table->string('recipient_name')->nullable();
            $table->string('recipient_phone')->nullable();
            $table->string('delivery_city')->nullable();
            $table->text('delivery_address')->nullable();
            $table->json('label_data')->nullable();
            $table->json('status_history')->nullable();
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('returned_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'tracking_number']);
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'order_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipments');
    }
};

