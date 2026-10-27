<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * @var list<string>
     */
    protected array $lunarTenantTables = [
        'lunar_products',
        'lunar_product_variants',
        'lunar_product_options',
        'lunar_product_option_values',
        'lunar_product_types',
        'lunar_prices',
        'lunar_collections',
        'lunar_collection_groups',
        'lunar_brands',
        'lunar_orders',
        'lunar_order_lines',
        'lunar_order_addresses',
        'lunar_carts',
        'lunar_cart_lines',
        'lunar_cart_addresses',
        'lunar_customers',
        'lunar_customer_groups',
        'lunar_addresses',
        'lunar_channels',
        'lunar_currencies',
        'lunar_discounts',
        'lunar_transactions',
        'lunar_tags',
        'lunar_attributes',
        'lunar_attribute_groups',
        'lunar_tax_classes',
        'lunar_tax_zones',
        'lunar_tax_rates',
    ];

    public function up(): void
    {
        // 1. Add tenant_id to Lunar tables
        foreach ($this->lunarTenantTables as $tableName) {
            if (Schema::hasTable($tableName) && ! Schema::hasColumn($tableName, 'tenant_id')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->string('tenant_id')->nullable()->index();
                });
            }
        }

        // 2. Add tenant_id to core tables if missing
        if (Schema::hasTable('users') && ! Schema::hasColumn('users', 'tenant_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('tenant_id')->nullable()->index();
            });
        }

        if (Schema::hasTable('idempotency_keys') && ! Schema::hasColumn('idempotency_keys', 'tenant_id')) {
            Schema::table('idempotency_keys', function (Blueprint $table) {
                $table->string('tenant_id')->nullable()->index();
            });
        }

        if (Schema::hasTable('settings') && ! Schema::hasColumn('settings', 'tenant_id')) {
            Schema::table('settings', function (Blueprint $table) {
                $table->string('tenant_id')->nullable()->index();
            });
        }
    }

    public function down(): void
    {
        foreach ($this->lunarTenantTables as $tableName) {
            if (Schema::hasTable($tableName) && Schema::hasColumn($tableName, 'tenant_id')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->dropColumn('tenant_id');
                });
            }
        }

        if (Schema::hasTable('users') && Schema::hasColumn('users', 'tenant_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('tenant_id');
            });
        }

        if (Schema::hasTable('idempotency_keys') && Schema::hasColumn('idempotency_keys', 'tenant_id')) {
            Schema::table('idempotency_keys', function (Blueprint $table) {
                $table->dropColumn('tenant_id');
            });
        }

        if (Schema::hasTable('settings') && Schema::hasColumn('settings', 'tenant_id')) {
            Schema::table('settings', function (Blueprint $table) {
                $table->dropColumn('tenant_id');
            });
        }
    }
};
