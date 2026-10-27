<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Governorate shipping rates per tenant
        Schema::create('tenant_shipping_rates', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id')->index();
            $table->foreignId('governorate_id')->constrained('governorates')->cascadeOnDelete();
            $table->bigInteger('rate_minor')->default(5000); // minor units (piasters)
            $table->string('delivery_time_ar')->default('خلال 2-3 أيام عمل');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'governorate_id']);
        });

        // 2. Coupons per tenant
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id')->index();
            $table->string('code')->index();
            $table->string('name');
            $table->string('type'); // 'percentage', 'fixed', 'free_shipping'
            $table->bigInteger('value_minor')->default(0); // percentage (e.g. 10) or piasters (e.g. 5000)
            $table->bigInteger('min_order_value_minor')->nullable();
            $table->bigInteger('max_discount_minor')->nullable();
            $table->unsignedInteger('max_uses')->nullable();
            $table->unsignedInteger('uses_count')->default(0);
            $table->unsignedInteger('max_uses_per_customer')->default(1);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->json('restricted_categories')->nullable();
            $table->json('restricted_products')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'code']);
        });

        // 3. Coupon redemptions log
        Schema::create('coupon_redemptions', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id')->index();
            $table->foreignId('coupon_id')->constrained('coupons')->cascadeOnDelete();
            $table->unsignedBigInteger('order_id')->nullable()->index();
            $table->char('customer_phone_hash', 64)->index();
            $table->bigInteger('discount_minor')->default(0);
            $table->timestamp('created_at')->useCurrent();

            $table->index(['tenant_id', 'coupon_id', 'customer_phone_hash']);
        });

        // 4. Extend lunar_orders with checkout, snapshot and quality fields
        if (Schema::hasTable('lunar_orders')) {
            Schema::table('lunar_orders', function (Blueprint $table) {
                if (! Schema::hasColumn('lunar_orders', 'customer_phone_e164')) {
                    $table->string('customer_phone_e164')->nullable()->index();
                }
                if (! Schema::hasColumn('lunar_orders', 'customer_phone_hash')) {
                    $table->char('customer_phone_hash', 64)->nullable()->index();
                }
                if (! Schema::hasColumn('lunar_orders', 'governorate_id')) {
                    $table->unsignedBigInteger('governorate_id')->nullable()->index();
                }
                if (! Schema::hasColumn('lunar_orders', 'cod_fee_minor')) {
                    $table->bigInteger('cod_fee_minor')->default(0);
                }
                if (! Schema::hasColumn('lunar_orders', 'payment_method')) {
                    $table->string('payment_method')->default('cod');
                }
                if (! Schema::hasColumn('lunar_orders', 'idempotency_key')) {
                    $table->string('idempotency_key')->nullable()->index();
                }
                if (! Schema::hasColumn('lunar_orders', 'price_snapshot')) {
                    $table->json('price_snapshot')->nullable();
                }
                if (! Schema::hasColumn('lunar_orders', 'quality_result')) {
                    $table->string('quality_result')->default('pass');
                }
                if (! Schema::hasColumn('lunar_orders', 'quality_flags')) {
                    $table->json('quality_flags')->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('lunar_orders')) {
            Schema::table('lunar_orders', function (Blueprint $table) {
                $columns = [
                    'customer_phone_e164',
                    'customer_phone_hash',
                    'governorate_id',
                    'cod_fee_minor',
                    'payment_method',
                    'idempotency_key',
                    'price_snapshot',
                    'quality_result',
                    'quality_flags',
                ];
                foreach ($columns as $column) {
                    if (Schema::hasColumn('lunar_orders', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        Schema::dropIfExists('coupon_redemptions');
        Schema::dropIfExists('coupons');
        Schema::dropIfExists('tenant_shipping_rates');
    }
};
