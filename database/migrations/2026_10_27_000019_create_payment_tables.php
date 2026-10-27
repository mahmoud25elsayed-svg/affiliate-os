<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Tenant Payment Gateways Configuration
        if (! Schema::hasTable('tenant_payment_gateways')) {
            Schema::create('tenant_payment_gateways', function (Blueprint $table) {
                $table->id();
                $table->string('tenant_id')->index();
                $table->string('gateway'); // 'paymob', 'fawry', 'kashier', 'fake'
                $table->boolean('is_enabled')->default(false);
                $table->string('mode')->default('test'); // 'test', 'live'
                $table->text('credentials')->nullable(); // encrypted array in Eloquent
                $table->timestamps();

                $table->unique(['tenant_id', 'gateway']);
            });
        }

        // 2. Payment Transactions
        if (! Schema::hasTable('payment_transactions')) {
            Schema::create('payment_transactions', function (Blueprint $table) {
                $table->id();
                $table->string('tenant_id')->index();
                $table->unsignedBigInteger('order_id')->index();
                $table->string('gateway');
                $table->string('transaction_ref')->index();
                $table->string('status')->default('pending'); // 'pending', 'paid', 'failed', 'refunded', 'partially_refunded'
                $table->bigInteger('amount_minor')->default(0);
                $table->bigInteger('refunded_amount_minor')->default(0);
                $table->string('currency', 3)->default('EGP');
                $table->string('payment_method')->nullable();
                $table->json('gateway_payload')->nullable();
                $table->text('error_message')->nullable();
                $table->timestamp('paid_at')->nullable();
                $table->timestamps();

                $table->unique(['tenant_id', 'transaction_ref']);
                $table->index(['tenant_id', 'order_id', 'status']);
            });
        }

        // 3. Customer Wallets
        if (! Schema::hasTable('customer_wallets')) {
            Schema::create('customer_wallets', function (Blueprint $table) {
                $table->id();
                $table->string('tenant_id')->index();
                $table->unsignedBigInteger('customer_id')->index();
                $table->bigInteger('balance_minor')->default(0);
                $table->string('currency', 3)->default('EGP');
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->unique(['tenant_id', 'customer_id']);
            });
        }

        // 4. Customer Wallet Transactions (Append-only Ledger)
        if (! Schema::hasTable('customer_wallet_transactions')) {
            Schema::create('customer_wallet_transactions', function (Blueprint $table) {
                $table->id();
                $table->string('tenant_id')->index();
                $table->foreignId('wallet_id')->constrained('customer_wallets')->cascadeOnDelete();
                $table->bigInteger('amount_minor'); // positive for credit, negative for debit
                $table->string('type'); // 'refund', 'reward', 'gift', 'purchase_payment', 'adjustment'
                $table->string('reference_id')->nullable()->index();
                $table->text('notes')->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->index(['tenant_id', 'wallet_id', 'type']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_wallet_transactions');
        Schema::dropIfExists('customer_wallets');
        Schema::dropIfExists('payment_transactions');
        Schema::dropIfExists('tenant_payment_gateways');
    }
};
