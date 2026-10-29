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
        Schema::create('consent_logs', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id')->nullable()->index();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('subject_type')->index(); // merchant, affiliate, customer
            $table->string('subject_identifier')->index(); // phone, email, or user/customer ID
            $table->string('document_type')->index(); // merchant_terms, dpa, affiliate_terms, privacy_policy, shipping_returns, aup
            $table->string('version')->default('1.0');
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('accepted_at')->useCurrent()->index();
            $table->timestamps();

            $table->index(['tenant_id', 'subject_type', 'subject_identifier', 'document_type'], 'idx_consent_lookup');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('consent_logs');
    }
};
