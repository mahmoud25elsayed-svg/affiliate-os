<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Flash Sales
        if (! Schema::hasTable('flash_sales')) {
            Schema::create('flash_sales', function (Blueprint $table) {
                $table->id();
                $table->string('tenant_id')->index();
                $table->string('title_ar');
                $table->string('title_en')->nullable();
                $table->string('slug')->index();
                $table->dateTime('starts_at')->index();
                $table->dateTime('ends_at')->index();
                $table->boolean('is_active')->default(true)->index();
                $table->string('banner_url')->nullable();
                $table->timestamps();

                $table->unique(['tenant_id', 'slug']);
            });
        }

        // 2. Flash Sale Items
        if (! Schema::hasTable('flash_sale_items')) {
            Schema::create('flash_sale_items', function (Blueprint $table) {
                $table->id();
                $table->string('tenant_id')->index();
                $table->unsignedBigInteger('flash_sale_id')->index();
                $table->unsignedBigInteger('product_id')->index();
                $table->unsignedBigInteger('product_variant_id')->index();
                $table->string('discount_type')->default('percentage'); // 'percentage', 'fixed_minor'
                $table->integer('discount_value'); // percent e.g. 25, or minor e.g. 5000 (50 EGP)
                $table->bigInteger('flash_price_minor'); // calculated discounted price for fast display/queries
                $table->integer('allocated_quantity')->default(0); // quantity reserved for flash sale
                $table->integer('sold_quantity')->default(0); // quantity sold under flash sale
                $table->timestamps();

                $table->unique(['tenant_id', 'flash_sale_id', 'product_variant_id'], 'flash_items_tenant_sale_variant_unique');
                $table->foreign('flash_sale_id')->references('id')->on('flash_sales')->cascadeOnDelete();
            });
        }

        // 3. Daily Deals (Deal of the Day)
        if (! Schema::hasTable('daily_deals')) {
            Schema::create('daily_deals', function (Blueprint $table) {
                $table->id();
                $table->string('tenant_id')->index();
                $table->unsignedBigInteger('product_id')->index();
                $table->unsignedBigInteger('product_variant_id')->nullable()->index();
                $table->string('title_ar');
                $table->string('discount_type')->default('percentage'); // 'percentage', 'fixed_minor'
                $table->integer('discount_value');
                $table->date('deal_date')->index();
                $table->boolean('is_active')->default(true)->index();
                $table->timestamps();

                $table->unique(['tenant_id', 'deal_date', 'product_id']);
            });
        }

        // 4. Product Bundles (Buy X Get Y / Combo Packages)
        if (! Schema::hasTable('product_bundles')) {
            Schema::create('product_bundles', function (Blueprint $table) {
                $table->id();
                $table->string('tenant_id')->index();
                $table->string('name_ar');
                $table->string('name_en')->nullable();
                $table->string('type')->default('buy_x_get_y'); // 'buy_x_get_y', 'combo_pack'
                $table->json('conditions'); // ['buy_variant_id' => ..., 'buy_qty' => ..., 'get_variant_id' => ..., 'get_qty' => ..., 'discount_percentage' => ...]
                $table->string('discount_type')->default('percentage');
                $table->integer('discount_value')->default(0);
                $table->dateTime('starts_at')->nullable();
                $table->dateTime('ends_at')->nullable();
                $table->boolean('is_active')->default(true)->index();
                $table->timestamps();

                $table->index(['tenant_id', 'is_active']);
            });
        }

        // 5. Customer Loyalty Accounts
        if (! Schema::hasTable('customer_loyalty_accounts')) {
            Schema::create('customer_loyalty_accounts', function (Blueprint $table) {
                $table->id();
                $table->string('tenant_id')->index();
                $table->unsignedBigInteger('customer_id')->index();
                $table->integer('points_balance')->default(0);
                $table->integer('total_earned_points')->default(0);
                $table->integer('total_redeemed_points')->default(0);
                $table->timestamps();

                $table->unique(['tenant_id', 'customer_id']);
            });
        }

        // 6. Customer Loyalty Transactions (Append-Only Audit)
        if (! Schema::hasTable('customer_loyalty_transactions')) {
            Schema::create('customer_loyalty_transactions', function (Blueprint $table) {
                $table->id();
                $table->string('tenant_id')->index();
                $table->unsignedBigInteger('loyalty_account_id')->index();
                $table->integer('points'); // signed (+ for earn, - for redeem)
                $table->integer('balance_after');
                $table->string('type'); // 'earn_order', 'redeem_order', 'adjustment', 'signup_bonus'
                $table->unsignedBigInteger('order_id')->nullable()->index();
                $table->string('notes')->nullable();
                $table->dateTime('created_at')->index();

                $table->foreign('loyalty_account_id')->references('id')->on('customer_loyalty_accounts')->cascadeOnDelete();
            });
        }

        // 7. Abandoned Carts
        if (! Schema::hasTable('abandoned_carts')) {
            Schema::create('abandoned_carts', function (Blueprint $table) {
                $table->id();
                $table->string('tenant_id')->index();
                $table->unsignedBigInteger('customer_id')->nullable()->index();
                $table->string('session_id')->nullable()->index();
                $table->string('recovery_token', 64)->index();
                $table->string('customer_name')->nullable();
                $table->string('phone_e164')->nullable()->index();
                $table->string('email')->nullable()->index();
                $table->json('cart_data'); // raw items, variants, prices snapshot
                $table->bigInteger('subtotal_minor')->default(0);
                $table->integer('items_count')->default(0);
                $table->dateTime('last_active_at')->index();
                $table->dateTime('reminded_at')->nullable();
                $table->dateTime('recovered_at')->nullable();
                $table->timestamps();

                $table->unique(['tenant_id', 'recovery_token']);
                $table->index(['tenant_id', 'last_active_at', 'recovered_at']);
            });
        }

        // 8. Newsletter Subscribers
        if (! Schema::hasTable('newsletter_subscribers')) {
            Schema::create('newsletter_subscribers', function (Blueprint $table) {
                $table->id();
                $table->string('tenant_id')->index();
                $table->string('email')->index();
                $table->string('name')->nullable();
                $table->boolean('is_active')->default(true)->index();
                $table->string('source')->default('storefront'); // 'storefront_footer', 'checkout_optin', 'popup'
                $table->dateTime('subscribed_at');
                $table->dateTime('unsubscribed_at')->nullable();
                $table->timestamps();

                $table->unique(['tenant_id', 'email']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('newsletter_subscribers');
        Schema::dropIfExists('abandoned_carts');
        Schema::dropIfExists('customer_loyalty_transactions');
        Schema::dropIfExists('customer_loyalty_accounts');
        Schema::dropIfExists('product_bundles');
        Schema::dropIfExists('daily_deals');
        Schema::dropIfExists('flash_sale_items');
        Schema::dropIfExists('flash_sales');
    }
};
