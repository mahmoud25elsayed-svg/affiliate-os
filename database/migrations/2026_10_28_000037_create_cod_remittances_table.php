<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cod_remittances', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id')->index();
            $table->foreignId('courier_account_id')->constrained('courier_accounts')->cascadeOnDelete();
            $table->string('reference')->index();
            $table->string('status')->default('draft')->index(); // draft, reconciled, discrepant, settled, cancelled
            $table->unsignedBigInteger('total_collected_minor')->default(0);
            $table->unsignedBigInteger('total_shipping_fees_minor')->default(0);
            $table->unsignedBigInteger('total_returned_fees_minor')->default(0);
            $table->bigInteger('net_payout_minor')->default(0);
            $table->unsignedInteger('matched_orders_count')->default(0);
            $table->unsignedInteger('discrepancy_orders_count')->default(0);
            $table->bigInteger('discrepancy_amount_minor')->default(0);
            $table->string('bank_reference')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('settled_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('settled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['tenant_id', 'courier_account_id', 'reference']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cod_remittances');
    }
};

