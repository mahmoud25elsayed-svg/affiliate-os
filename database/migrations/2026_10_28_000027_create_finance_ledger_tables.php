<?php

declare(strict_types=1);

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
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->string('code', 100);
            $table->string('owner_type')->nullable();
            $table->unsignedBigInteger('owner_id')->nullable();
            $table->string('kind', 40);
            $table->string('name')->nullable();
            $table->char('currency', 3)->default('EGP');
            $table->bigInteger('balance_minor')->default(0);
            $table->timestamps();

            $table->unique(['tenant_id', 'code']);
            $table->index(['tenant_id', 'owner_type', 'owner_id', 'kind']);
        });

        Schema::create('journal_entries', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->string('type', 40);
            $table->string('idempotency_key', 120);
            $table->string('ref_type', 100)->nullable();
            $table->unsignedBigInteger('ref_id')->nullable();
            $table->string('description', 255);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['tenant_id', 'idempotency_key']);
            $table->index(['tenant_id', 'ref_type', 'ref_id']);
            $table->index(['tenant_id', 'type', 'created_at']);
        });

        Schema::create('ledger_lines', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->foreignId('journal_entry_id')->constrained('journal_entries')->cascadeOnDelete();
            $table->foreignId('account_id')->constrained('accounts')->restrictOnDelete();
            $table->bigInteger('amount_minor'); // Signed: positive = credit, negative = debit
            $table->timestamp('created_at')->useCurrent();

            $table->index(['tenant_id', 'account_id', 'id']);
            $table->index(['tenant_id', 'journal_entry_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ledger_lines');
        Schema::dropIfExists('journal_entries');
        Schema::dropIfExists('accounts');
    }
};
