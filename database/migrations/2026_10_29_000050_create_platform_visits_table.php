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
        Schema::create('platform_visits', function (Blueprint $table) {
            $table->id();
            $table->string('path', 255)->index();
            $table->string('referrer_domain', 255)->nullable()->index();
            $table->string('referrer_type', 50)->default('direct')->index(); // direct, search, social, internal, affiliate
            $table->string('device_category', 20)->default('desktop')->index(); // desktop, mobile, tablet
            $table->string('country_code', 10)->default('EG')->index();
            $table->string('visitor_hash', 64)->index(); // Privacy-safe daily salted hash
            $table->boolean('converted')->default(false)->index();
            $table->string('conversion_type', 50)->nullable()->index();
            $table->timestamp('visited_at')->useCurrent()->index();
            $table->timestamps();

            $table->index(['visited_at', 'converted'], 'idx_visits_date_conversion');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('platform_visits');
    }
};
