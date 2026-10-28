<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Suppliers Table
        if (! Schema::hasTable('suppliers')) {
            Schema::create('suppliers', function (Blueprint $table) {
                $table->id();
                $table->string('tenant_id')->index();
                $table->string('name');
                $table->string('company_name')->nullable();
                $table->string('phone')->index();
                $table->string('email')->nullable();
                $table->string('tax_id')->nullable();
                $table->text('address')->nullable();
                $table->string('status', 30)->default('active')->index(); // active, suspended
                $table->unsignedSmallInteger('default_lead_time_days')->default(2);
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index(['tenant_id', 'status']);
                $table->index(['tenant_id', 'phone']);
            });
        }

        // 2. Supplier Supply Batches Table
        if (! Schema::hasTable('supplier_batches')) {
            Schema::create('supplier_batches', function (Blueprint $table) {
                $table->id();
                $table->string('tenant_id')->index();
                $table->string('batch_number')->index();
                $table->foreignId('supplier_id')->constrained('suppliers')->cascadeOnDelete();
                $table->unsignedBigInteger('product_variant_id')->index();
                $table->unsignedInteger('quantity_supplied');
                $table->unsignedBigInteger('unit_cost_minor');
                $table->unsignedBigInteger('total_cost_minor');
                $table->string('status', 30)->default('received')->index(); // pending, received, rejected
                $table->timestamp('received_at')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->unique(['tenant_id', 'batch_number']);
                $table->index(['tenant_id', 'supplier_id']);
                $table->index(['tenant_id', 'product_variant_id']);
            });
        }

        // 3. Add supplier_id and min/max commission overrides to wholesale_prices
        if (Schema::hasTable('wholesale_prices')) {
            Schema::table('wholesale_prices', function (Blueprint $table) {
                if (! Schema::hasColumn('wholesale_prices', 'supplier_id')) {
                    $table->foreignId('supplier_id')->nullable()->after('product_variant_id')->constrained('suppliers')->nullOnDelete();
                }
                if (! Schema::hasColumn('wholesale_prices', 'min_commission_minor')) {
                    $table->unsignedBigInteger('min_commission_minor')->nullable()->after('max_price_minor');
                }
                if (! Schema::hasColumn('wholesale_prices', 'max_commission_minor')) {
                    $table->unsignedBigInteger('max_commission_minor')->nullable()->after('min_commission_minor');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('wholesale_prices')) {
            Schema::table('wholesale_prices', function (Blueprint $table) {
                if (Schema::hasColumn('wholesale_prices', 'supplier_id')) {
                    $table->dropForeign(['supplier_id']);
                    $table->dropColumn('supplier_id');
                }
                if (Schema::hasColumn('wholesale_prices', 'min_commission_minor')) {
                    $table->dropColumn('min_commission_minor');
                }
                if (Schema::hasColumn('wholesale_prices', 'max_commission_minor')) {
                    $table->dropColumn('max_commission_minor');
                }
            });
        }

        Schema::dropIfExists('supplier_batches');
        Schema::dropIfExists('suppliers');
    }
};
