<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cod_remittance_items', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id')->index();
            $table->foreignId('cod_remittance_id')->constrained('cod_remittances')->cascadeOnDelete();
            $table->foreignId('shipment_id')->nullable()->constrained('shipments')->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('lunar_orders')->nullOnDelete();
            $table->string('tracking_number')->index();
            $table->string('reported_status')->default('delivered');
            $table->unsignedBigInteger('reported_cod_minor')->default(0);
            $table->unsignedBigInteger('expected_cod_minor')->default(0);
            $table->bigInteger('cod_discrepancy_minor')->default(0);
            $table->unsignedBigInteger('shipping_fee_minor')->default(0);
            $table->unsignedBigInteger('expected_shipping_fee_minor')->default(0);
            $table->bigInteger('fee_discrepancy_minor')->default(0);
            $table->string('match_status')->default('matched')->index(); // matched, amount_mismatch, fee_mismatch, not_found, already_settled
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cod_remittance_items');
    }
};

