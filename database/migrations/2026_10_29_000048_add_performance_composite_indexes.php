<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. lunar_orders
        if (Schema::hasTable('lunar_orders')) {
            Schema::table('lunar_orders', function (Blueprint $table) {
                $table->index(['tenant_id', 'status', 'created_at'], 'idx_orders_tenant_status_created');
                $table->index(['tenant_id', 'created_at'], 'idx_orders_tenant_created');
            });
        }

        // 2. shipments
        if (Schema::hasTable('shipments')) {
            Schema::table('shipments', function (Blueprint $table) {
                $table->index(['tenant_id', 'status', 'created_at'], 'idx_shipments_tenant_status_created');
                $table->index(['tenant_id', 'tracking_number'], 'idx_shipments_tenant_tracking');
                $table->index(['tenant_id', 'courier_account_id'], 'idx_shipments_tenant_courier');
            });
        }

        // 3. payout_requests
        if (Schema::hasTable('payout_requests')) {
            Schema::table('payout_requests', function (Blueprint $table) {
                $table->index(['tenant_id', 'status', 'created_at'], 'idx_payouts_tenant_status_created');
                $table->index(['tenant_id', 'affiliate_id', 'status'], 'idx_payouts_tenant_affiliate_status');
            });
        }

        // 4. tickets
        if (Schema::hasTable('tickets')) {
            Schema::table('tickets', function (Blueprint $table) {
                $table->index(['tenant_id', 'level', 'status', 'created_at'], 'idx_tickets_tenant_level_status');
                $table->index(['tenant_id', 'sla_due_at'], 'idx_tickets_tenant_sla_due');
                $table->index(['tenant_id', 'affiliate_id'], 'idx_tickets_tenant_affiliate');
            });
        }

        // 5. call_attempts
        if (Schema::hasTable('call_attempts')) {
            Schema::table('call_attempts', function (Blueprint $table) {
                $table->index(['tenant_id', 'order_id', 'created_at'], 'idx_call_attempts_tenant_order_created');
                $table->index(['tenant_id', 'user_id', 'created_at'], 'idx_call_attempts_tenant_user_created');
            });
        }

        // 6. tracking_links
        if (Schema::hasTable('tracking_links')) {
            Schema::table('tracking_links', function (Blueprint $table) {
                $table->index(['tenant_id', 'slug'], 'idx_tracking_links_tenant_slug');
                $table->index(['tenant_id', 'affiliate_id'], 'idx_tracking_links_tenant_affiliate');
            });
        }

        // 7. ad_conversion_events
        if (Schema::hasTable('ad_conversion_events')) {
            Schema::table('ad_conversion_events', function (Blueprint $table) {
                $table->index(['tenant_id', 'status', 'created_at'], 'idx_ad_events_tenant_status_created');
            });
        }

        // 8. webhook_deliveries
        if (Schema::hasTable('webhook_deliveries')) {
            Schema::table('webhook_deliveries', function (Blueprint $table) {
                $table->index(['tenant_id', 'status', 'created_at'], 'idx_wh_deliveries_tenant_status_created');
            });
        }

        // 9. inventory_balances
        if (Schema::hasTable('inventory_balances')) {
            Schema::table('inventory_balances', function (Blueprint $table) {
                $table->index(['tenant_id', 'product_variant_id'], 'idx_inv_balances_tenant_variant');
            });
        }

        // 10. affiliates
        if (Schema::hasTable('affiliates')) {
            Schema::table('affiliates', function (Blueprint $table) {
                $table->index(['tenant_id', 'status', 'created_at'], 'idx_affiliates_tenant_status_created');
                $table->index(['tenant_id', 'phone'], 'idx_affiliates_tenant_phone');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('lunar_orders')) {
            Schema::table('lunar_orders', function (Blueprint $table) {
                $table->dropIndex('idx_orders_tenant_status_created');
                $table->dropIndex('idx_orders_tenant_created');
            });
        }

        if (Schema::hasTable('shipments')) {
            Schema::table('shipments', function (Blueprint $table) {
                $table->dropIndex('idx_shipments_tenant_status_created');
                $table->dropIndex('idx_shipments_tenant_tracking');
                $table->dropIndex('idx_shipments_tenant_courier');
            });
        }

        if (Schema::hasTable('payout_requests')) {
            Schema::table('payout_requests', function (Blueprint $table) {
                $table->dropIndex('idx_payouts_tenant_status_created');
                $table->dropIndex('idx_payouts_tenant_affiliate_status');
            });
        }

        if (Schema::hasTable('tickets')) {
            Schema::table('tickets', function (Blueprint $table) {
                $table->dropIndex('idx_tickets_tenant_level_status');
                $table->dropIndex('idx_tickets_tenant_sla_due');
                $table->dropIndex('idx_tickets_tenant_affiliate');
            });
        }

        if (Schema::hasTable('call_attempts')) {
            Schema::table('call_attempts', function (Blueprint $table) {
                $table->dropIndex('idx_call_attempts_tenant_order_created');
                $table->dropIndex('idx_call_attempts_tenant_user_created');
            });
        }

        if (Schema::hasTable('tracking_links')) {
            Schema::table('tracking_links', function (Blueprint $table) {
                $table->dropIndex('idx_tracking_links_tenant_slug');
                $table->dropIndex('idx_tracking_links_tenant_affiliate');
            });
        }

        if (Schema::hasTable('ad_conversion_events')) {
            Schema::table('ad_conversion_events', function (Blueprint $table) {
                $table->dropIndex('idx_ad_events_tenant_status_created');
            });
        }

        if (Schema::hasTable('webhook_deliveries')) {
            Schema::table('webhook_deliveries', function (Blueprint $table) {
                $table->dropIndex('idx_wh_deliveries_tenant_status_created');
            });
        }

        if (Schema::hasTable('inventory_balances')) {
            Schema::table('inventory_balances', function (Blueprint $table) {
                $table->dropIndex('idx_inv_balances_tenant_variant');
            });
        }

        if (Schema::hasTable('affiliates')) {
            Schema::table('affiliates', function (Blueprint $table) {
                $table->dropIndex('idx_affiliates_tenant_status_created');
                $table->dropIndex('idx_affiliates_tenant_phone');
            });
        }
    }
};
