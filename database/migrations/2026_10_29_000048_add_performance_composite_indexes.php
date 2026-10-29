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
            try {
                Schema::table('lunar_orders', function (Blueprint $table) {
                    $table->index(['tenant_id', 'status', 'created_at'], 'idx_orders_tenant_status_created');
                    $table->index(['tenant_id', 'created_at'], 'idx_orders_tenant_created');
                });
            } catch (Throwable) {
            }
        }

        // 2. shipments
        if (Schema::hasTable('shipments')) {
            try {
                Schema::table('shipments', function (Blueprint $table) {
                    $table->index(['tenant_id', 'status', 'created_at'], 'idx_shipments_tenant_status_created');
                    $table->index(['tenant_id', 'tracking_number'], 'idx_shipments_tenant_tracking');
                    $table->index(['tenant_id', 'courier_account_id'], 'idx_shipments_tenant_courier');
                });
            } catch (Throwable) {
            }
        }

        // 3. payout_requests
        if (Schema::hasTable('payout_requests')) {
            try {
                Schema::table('payout_requests', function (Blueprint $table) {
                    $table->index(['tenant_id', 'status', 'created_at'], 'idx_payouts_tenant_status_created');
                    $table->index(['tenant_id', 'affiliate_id', 'status'], 'idx_payouts_tenant_affiliate_status');
                });
            } catch (Throwable) {
            }
        }

        // 4. tickets
        if (Schema::hasTable('tickets')) {
            try {
                Schema::table('tickets', function (Blueprint $table) {
                    $table->index(['tenant_id', 'level', 'status', 'created_at'], 'idx_tickets_tenant_level_status');
                    $table->index(['tenant_id', 'sla_due_at'], 'idx_tickets_tenant_sla_due');
                    $table->index(['tenant_id', 'affiliate_id'], 'idx_tickets_tenant_affiliate');
                });
            } catch (Throwable) {
            }
        }

        // 5. call_attempts
        if (Schema::hasTable('call_attempts')) {
            try {
                Schema::table('call_attempts', function (Blueprint $table) {
                    $table->index(['tenant_id', 'order_id', 'created_at'], 'idx_call_attempts_tenant_order_created');
                    $table->index(['tenant_id', 'user_id', 'created_at'], 'idx_call_attempts_tenant_user_created');
                });
            } catch (Throwable) {
            }
        }

        // 6. tracking_links
        if (Schema::hasTable('tracking_links')) {
            try {
                Schema::table('tracking_links', function (Blueprint $table) {
                    $table->index(['tenant_id', 'code'], 'idx_tracking_links_tenant_code');
                });
            } catch (Throwable) {
            }
        }

        // 7. ad_conversion_events
        if (Schema::hasTable('ad_conversion_events')) {
            try {
                Schema::table('ad_conversion_events', function (Blueprint $table) {
                    $table->index(['tenant_id', 'status', 'created_at'], 'idx_ad_events_tenant_status_created');
                });
            } catch (Throwable) {
            }
        }

        // 8. webhook_deliveries
        if (Schema::hasTable('webhook_deliveries')) {
            try {
                Schema::table('webhook_deliveries', function (Blueprint $table) {
                    $table->index(['tenant_id', 'status', 'created_at'], 'idx_wh_deliveries_tenant_status_created');
                });
            } catch (Throwable) {
            }
        }

        // 9. inventory_balances
        if (Schema::hasTable('inventory_balances')) {
            try {
                Schema::table('inventory_balances', function (Blueprint $table) {
                    $table->index(['tenant_id', 'product_variant_id'], 'idx_inv_balances_tenant_variant');
                });
            } catch (Throwable) {
            }
        }

        // 10. affiliates
        if (Schema::hasTable('affiliates')) {
            try {
                Schema::table('affiliates', function (Blueprint $table) {
                    $table->index(['tenant_id', 'status', 'created_at'], 'idx_affiliates_tenant_status_created');
                    $table->index(['tenant_id', 'phone'], 'idx_affiliates_tenant_phone');
                });
            } catch (Throwable) {
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('lunar_orders')) {
            try {
                Schema::table('lunar_orders', function (Blueprint $table) {
                    $table->dropIndex('idx_orders_tenant_status_created');
                    $table->dropIndex('idx_orders_tenant_created');
                });
            } catch (Throwable) {
            }
        }

        if (Schema::hasTable('shipments')) {
            try {
                Schema::table('shipments', function (Blueprint $table) {
                    $table->dropIndex('idx_shipments_tenant_status_created');
                    $table->dropIndex('idx_shipments_tenant_tracking');
                    $table->dropIndex('idx_shipments_tenant_courier');
                });
            } catch (Throwable) {
            }
        }

        if (Schema::hasTable('payout_requests')) {
            try {
                Schema::table('payout_requests', function (Blueprint $table) {
                    $table->dropIndex('idx_payouts_tenant_status_created');
                    $table->dropIndex('idx_payouts_tenant_affiliate_status');
                });
            } catch (Throwable) {
            }
        }

        if (Schema::hasTable('tickets')) {
            try {
                Schema::table('tickets', function (Blueprint $table) {
                    $table->dropIndex('idx_tickets_tenant_level_status');
                    $table->dropIndex('idx_tickets_tenant_sla_due');
                    $table->dropIndex('idx_tickets_tenant_affiliate');
                });
            } catch (Throwable) {
            }
        }

        if (Schema::hasTable('call_attempts')) {
            try {
                Schema::table('call_attempts', function (Blueprint $table) {
                    $table->dropIndex('idx_call_attempts_tenant_order_created');
                    $table->dropIndex('idx_call_attempts_tenant_user_created');
                });
            } catch (Throwable) {
            }
        }

        if (Schema::hasTable('tracking_links')) {
            try {
                Schema::table('tracking_links', function (Blueprint $table) {
                    $table->dropIndex('idx_tracking_links_tenant_code');
                });
            } catch (Throwable) {
            }
        }

        if (Schema::hasTable('ad_conversion_events')) {
            try {
                Schema::table('ad_conversion_events', function (Blueprint $table) {
                    $table->dropIndex('idx_ad_events_tenant_status_created');
                });
            } catch (Throwable) {
            }
        }

        if (Schema::hasTable('webhook_deliveries')) {
            try {
                Schema::table('webhook_deliveries', function (Blueprint $table) {
                    $table->dropIndex('idx_wh_deliveries_tenant_status_created');
                });
            } catch (Throwable) {
            }
        }

        if (Schema::hasTable('inventory_balances')) {
            try {
                Schema::table('inventory_balances', function (Blueprint $table) {
                    $table->dropIndex('idx_inv_balances_tenant_variant');
                });
            } catch (Throwable) {
            }
        }

        if (Schema::hasTable('affiliates')) {
            try {
                Schema::table('affiliates', function (Blueprint $table) {
                    $table->dropIndex('idx_affiliates_tenant_status_created');
                    $table->dropIndex('idx_affiliates_tenant_phone');
                });
            } catch (Throwable) {
            }
        }
    }
};
