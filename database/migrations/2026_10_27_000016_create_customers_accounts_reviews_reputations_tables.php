<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Customers Table
        if (! Schema::hasTable('customers')) {
            Schema::create('customers', function (Blueprint $table) {
                $table->id();
                $table->string('tenant_id')->index();
                $table->string('first_name');
                $table->string('last_name')->nullable();
                $table->string('email')->nullable()->index();
                $table->string('phone_e164')->nullable()->index();
                $table->string('phone_hash', 64)->nullable()->index();
                $table->string('password')->nullable();
                $table->timestamp('phone_verified_at')->nullable();
                $table->timestamp('email_verified_at')->nullable();
                $table->boolean('is_active')->default(true);
                $table->rememberToken();
                $table->timestamp('anonymized_at')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['tenant_id', 'phone_hash']);
                $table->index(['tenant_id', 'email']);
            });
        }

        // 2. Customer Addresses
        if (! Schema::hasTable('customer_addresses')) {
            Schema::create('customer_addresses', function (Blueprint $table) {
                $table->id();
                $table->string('tenant_id')->index();
                $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
                $table->string('name');
                $table->string('phone');
                $table->foreignId('governorate_id')->constrained('governorates');
                $table->string('city');
                $table->text('address_line');
                $table->string('postal_code')->nullable();
                $table->string('notes')->nullable();
                $table->boolean('is_default')->default(false);
                $table->timestamps();

                $table->index(['tenant_id', 'customer_id']);
            });
        }

        // 3. Customer Wishlists
        if (! Schema::hasTable('customer_wishlists')) {
            Schema::create('customer_wishlists', function (Blueprint $table) {
                $table->id();
                $table->string('tenant_id')->index();
                $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
                $table->foreignId('product_id')->constrained('lunar_products')->cascadeOnDelete();
                $table->timestamp('created_at')->useCurrent();

                $table->unique(['tenant_id', 'customer_id', 'product_id'], 'customer_wishlist_unique');
            });
        }

        // 4. Product Reviews
        if (! Schema::hasTable('product_reviews')) {
            Schema::create('product_reviews', function (Blueprint $table) {
                $table->id();
                $table->string('tenant_id')->index();
                $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
                $table->foreignId('product_id')->constrained('lunar_products')->cascadeOnDelete();
                $table->foreignId('order_id')->nullable()->constrained('lunar_orders')->nullOnDelete();
                $table->unsignedTinyInteger('rating'); // 1 to 5
                $table->string('title')->nullable();
                $table->text('comment');
                $table->string('status', 20)->default('pending')->index(); // pending, approved, rejected
                $table->string('rejection_reason')->nullable();
                $table->boolean('is_verified_purchase')->default(true);
                $table->timestamps();

                $table->index(['tenant_id', 'product_id', 'status']);
                $table->index(['tenant_id', 'customer_id']);
            });
        }

        // 5. Customer Reputations (phone_hash based)
        if (! Schema::hasTable('customer_reputations')) {
            Schema::create('customer_reputations', function (Blueprint $table) {
                $table->id();
                $table->string('phone_hash', 64)->index();
                $table->string('tenant_id')->nullable()->index();
                $table->unsignedInteger('delivered_orders_count')->default(0);
                $table->unsignedInteger('refused_orders_count')->default(0);
                $table->unsignedInteger('total_orders_count')->default(0);
                $table->timestamp('last_seen_at')->nullable();
                $table->timestamps();

                $table->unique(['phone_hash', 'tenant_id'], 'reputation_phone_tenant_unique');
            });
        }

        // 6. Add storefront_customer_id to lunar_orders
        if (Schema::hasTable('lunar_orders') && ! Schema::hasColumn('lunar_orders', 'storefront_customer_id')) {
            Schema::table('lunar_orders', function (Blueprint $table) {
                $table->foreignId('storefront_customer_id')->nullable()->after('id')->constrained('customers')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('lunar_orders') && Schema::hasColumn('lunar_orders', 'storefront_customer_id')) {
            Schema::table('lunar_orders', function (Blueprint $table) {
                $table->dropForeign(['storefront_customer_id']);
                $table->dropColumn('storefront_customer_id');
            });
        }

        Schema::dropIfExists('customer_reputations');
        Schema::dropIfExists('product_reviews');
        Schema::dropIfExists('customer_wishlists');
        Schema::dropIfExists('customer_addresses');
        Schema::dropIfExists('customers');
    }
};
