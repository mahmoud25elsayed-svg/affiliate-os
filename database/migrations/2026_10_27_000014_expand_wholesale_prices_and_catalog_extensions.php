<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wholesale_prices', function (Blueprint $table) {
            $table->unsignedBigInteger('min_price_minor')->default(0)->after('wholesale_price_minor');
            $table->unsignedBigInteger('max_price_minor')->default(0)->after('min_price_minor');
            $table->unsignedBigInteger('compare_at_price_minor')->nullable()->after('max_price_minor');
            $table->boolean('is_digital')->default(false)->after('compare_at_price_minor');
            $table->string('digital_file_path')->nullable()->after('is_digital');
            $table->string('status')->default('active')->index()->after('digital_file_path'); // draft, active, paused, archived
            $table->text('pause_reason')->nullable()->after('status');
        });

        // Add check constraint if driver supports it
        $driver = Schema::getConnection()->getDriverName();
        if (in_array($driver, ['sqlite', 'mysql', 'pgsql'], true)) {
            try {
                DB::statement(
                    'ALTER TABLE wholesale_prices ADD CONSTRAINT chk_price_range CHECK (max_price_minor >= min_price_minor AND min_price_minor >= wholesale_price_minor)'
                );
            } catch (Throwable $e) {
                // SQLite table alter check constraints might be ignored or handled by model validation
            }
        }
    }

    public function down(): void
    {
        Schema::table('wholesale_prices', function (Blueprint $table) {
            $table->dropColumn([
                'min_price_minor',
                'max_price_minor',
                'compare_at_price_minor',
                'is_digital',
                'digital_file_path',
                'status',
                'pause_reason',
            ]);
        });
    }
};
