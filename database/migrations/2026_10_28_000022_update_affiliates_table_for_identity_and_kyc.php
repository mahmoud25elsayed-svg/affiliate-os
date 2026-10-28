<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('affiliates', function (Blueprint $table) {
            $table->string('email')->nullable()->after('name');
            $table->string('phone')->nullable()->after('email');
            $table->string('password')->nullable()->after('phone');
            $table->text('status_reason')->nullable()->after('status');
            $table->foreignId('referred_by_id')->nullable()->after('commission_rate')->constrained('affiliates')->nullOnDelete();

            // Authentication & OTP
            $table->timestamp('phone_verified_at')->nullable()->after('referred_by_id');
            $table->timestamp('email_verified_at')->nullable()->after('phone_verified_at');
            $table->string('otp_code', 16)->nullable()->after('email_verified_at');
            $table->timestamp('otp_expires_at')->nullable()->after('otp_code');

            // KYC Verification
            $table->string('national_id_number', 32)->nullable()->after('otp_expires_at');
            $table->string('id_card_front_path')->nullable()->after('national_id_number');
            $table->string('id_card_back_path')->nullable()->after('id_card_front_path');
            $table->string('kyc_status', 32)->default('unsubmitted')->after('id_card_back_path');
            $table->text('kyc_rejected_reason')->nullable()->after('kyc_status');
            $table->timestamp('kyc_reviewed_at')->nullable()->after('kyc_rejected_reason');
            $table->unsignedBigInteger('kyc_reviewed_by')->nullable()->after('kyc_reviewed_at');

            // Payout Details & Freeze
            $table->string('payout_method', 32)->nullable()->after('kyc_reviewed_by');
            $table->text('payout_details')->nullable()->after('payout_method');
            $table->timestamp('payouts_frozen_until')->nullable()->after('payout_details');

            $table->rememberToken()->after('payouts_frozen_until');

            $table->index(['tenant_id', 'email']);
            $table->index(['tenant_id', 'phone']);
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'kyc_status']);
        });
    }

    public function down(): void
    {
        Schema::table('affiliates', function (Blueprint $table) {
            $table->dropForeign(['referred_by_id']);
            $table->dropColumn([
                'email',
                'phone',
                'password',
                'status_reason',
                'referred_by_id',
                'phone_verified_at',
                'email_verified_at',
                'otp_code',
                'otp_expires_at',
                'national_id_number',
                'id_card_front_path',
                'id_card_back_path',
                'kyc_status',
                'kyc_rejected_reason',
                'kyc_reviewed_at',
                'kyc_reviewed_by',
                'payout_method',
                'payout_details',
                'payouts_frozen_until',
                'remember_token',
            ]);
        });
    }
};
