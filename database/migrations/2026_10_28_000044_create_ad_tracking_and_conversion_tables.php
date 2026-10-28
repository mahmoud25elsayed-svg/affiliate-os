<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add pixel IDs to affiliates
        if (Schema::hasTable('affiliates')) {
            Schema::table('affiliates', function (Blueprint $table) {
                if (! Schema::hasColumn('affiliates', 'meta_pixel_id')) {
                    $table->string('meta_pixel_id', 50)->nullable()->after('payout_details');
                }
                if (! Schema::hasColumn('affiliates', 'tiktok_pixel_id')) {
                    $table->string('tiktok_pixel_id', 50)->nullable()->after('meta_pixel_id');
                }
            });
        }

        // Conversion event audit log (Diagnostic panel)
        if (! Schema::hasTable('ad_conversion_events')) {
            Schema::create('ad_conversion_events', function (Blueprint $table) {
                $table->id();
                $table->string('tenant_id')->index();
                $table->unsignedBigInteger('order_id')->nullable()->index();
                $table->unsignedBigInteger('affiliate_id')->nullable()->index();
                $table->string('platform', 20)->index(); // meta, tiktok
                $table->string('pixel_id', 50)->nullable();
                $table->string('event_name', 50)->index(); // ViewContent, AddToCart, InitiateCheckout, Purchase, Delivered
                $table->string('event_id', 100)->index(); // Deduplication event_id
                $table->json('payload');
                $table->string('status', 20)->default('pending')->index(); // pending, sent, failed
                $table->unsignedSmallInteger('response_code')->nullable();
                $table->text('response_body')->nullable();
                $table->text('error_message')->nullable();
                $table->timestamp('sent_at')->nullable();
                $table->timestamps();

                $table->index(['tenant_id', 'created_at']);
                $table->index(['platform', 'event_name']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ad_conversion_events');

        if (Schema::hasTable('affiliates')) {
            Schema::table('affiliates', function (Blueprint $table) {
                if (Schema::hasColumn('affiliates', 'tiktok_pixel_id')) {
                    $table->dropColumn('tiktok_pixel_id');
                }
                if (Schema::hasColumn('affiliates', 'meta_pixel_id')) {
                    $table->dropColumn('meta_pixel_id');
                }
            });
        }
    }
};
