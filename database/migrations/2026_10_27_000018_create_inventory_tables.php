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
        // 1. Warehouses
        if (! Schema::hasTable('warehouses')) {
            Schema::create('warehouses', function (Blueprint $table) {
                $table->id();
                $table->string('tenant_id')->index();
                $table->string('name');
                $table->string('code');
                $table->string('type')->default('own'); // 'own', '3pl', 'supplier'
                $table->string('address')->nullable();
                $table->unsignedBigInteger('governorate_id')->nullable()->index();
                $table->boolean('is_default')->default(false);
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->unique(['tenant_id', 'code']);
            });
        }

        // 2. Inventory Balances
        if (! Schema::hasTable('inventory_balances')) {
            Schema::create('inventory_balances', function (Blueprint $table) {
                $table->id();
                $table->string('tenant_id')->index();
                $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
                $table->unsignedBigInteger('product_variant_id')->index();
                $table->integer('on_hand')->default(0);
                $table->integer('reserved')->default(0);
                $table->integer('low_stock_threshold')->default(5);
                $table->timestamps();

                $table->unique(['tenant_id', 'warehouse_id', 'product_variant_id'], 'inv_balances_tenant_wh_variant_unique');
            });

            // Add CHECK constraint on engines that support it via ALTER TABLE
            try {
                DB::statement('ALTER TABLE inventory_balances ADD CONSTRAINT chk_inv_balance_limits CHECK (reserved >= 0 AND reserved <= on_hand AND on_hand >= 0)');
            } catch (Throwable) {
                // SQLite does not support ALTER TABLE ADD CONSTRAINT; constraint is enforced in Eloquent and Actions
            }
        }

        // 3. Inventory Movements (Append-only Audit)
        if (! Schema::hasTable('inventory_movements')) {
            Schema::create('inventory_movements', function (Blueprint $table) {
                $table->id();
                $table->string('tenant_id')->index();
                $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
                $table->unsignedBigInteger('product_variant_id')->index();
                $table->integer('quantity_delta')->default(0); // change in on_hand
                $table->integer('reserved_delta')->default(0); // change in reserved
                $table->string('reason'); // 'inbound', 'reserve', 'release', 'ship', 'return', 'adjust', 'count', 'transfer'
                $table->string('reference_type')->nullable(); // 'order', 'transfer', 'count_session', 'supplier_receipt'
                $table->string('reference_id')->nullable();
                $table->string('reference_key')->nullable()->index(); // Idempotency key
                $table->string('actor_type')->default('system'); // 'system', 'staff'
                $table->string('actor_id')->nullable();
                $table->text('notes')->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->index(['tenant_id', 'product_variant_id', 'warehouse_id'], 'inv_movements_tenant_variant_wh_index');
                $table->unique(['tenant_id', 'reference_key']);
            });
        }

        // 4. Stock Count Sessions (Periodic Inventory Audits)
        if (! Schema::hasTable('stock_count_sessions')) {
            Schema::create('stock_count_sessions', function (Blueprint $table) {
                $table->id();
                $table->string('tenant_id')->index();
                $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
                $table->string('reference')->index();
                $table->string('status')->default('draft'); // 'draft', 'approved', 'cancelled'
                $table->unsignedBigInteger('creator_id')->nullable();
                $table->unsignedBigInteger('approver_id')->nullable();
                $table->text('notes')->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->timestamps();

                $table->unique(['tenant_id', 'reference']);
            });
        }

        // 5. Stock Count Session Items
        if (! Schema::hasTable('stock_count_session_items')) {
            Schema::create('stock_count_session_items', function (Blueprint $table) {
                $table->id();
                $table->string('tenant_id')->index();
                $table->foreignId('session_id')->constrained('stock_count_sessions')->cascadeOnDelete();
                $table->unsignedBigInteger('product_variant_id')->index();
                $table->integer('system_on_hand')->default(0);
                $table->integer('counted_on_hand')->default(0);
                $table->integer('discrepancy')->default(0); // counted - system
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_count_session_items');
        Schema::dropIfExists('stock_count_sessions');
        Schema::dropIfExists('inventory_movements');
        Schema::dropIfExists('inventory_balances');
        Schema::dropIfExists('warehouses');
    }
};
