<?php

declare(strict_types=1);

namespace App\Support\Tenancy;

use App\Models\Tenant;
use Illuminate\Support\Facades\DB;

class TenantDataService
{
    /**
     * @var list<string>
     */
    protected array $tenantScopedTables = [
        'affiliates',
        'wholesale_prices',
        'idempotency_keys',
        'audit_logs',
        'settings',
        'users',
        'lunar_products',
        'lunar_product_variants',
        'lunar_product_options',
        'lunar_product_option_values',
        'lunar_prices',
        'lunar_collections',
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

    public function archive(Tenant $tenant): void
    {
        $tenant->update(['status' => 'archived']);
    }

    public function safeDelete(Tenant $tenant): void
    {
        $tenantId = (string) $tenant->getTenantKey();

        DB::transaction(function () use ($tenant, $tenantId) {
            foreach ($this->tenantScopedTables as $table) {
                if (DB::getSchemaBuilder()->hasTable($table) && DB::getSchemaBuilder()->hasColumn($table, 'tenant_id')) {
                    DB::table($table)->where('tenant_id', $tenantId)->delete();
                }
            }

            $tenant->domains()->delete();
            $tenant->delete();
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function export(Tenant $tenant): array
    {
        $tenantId = (string) $tenant->getTenantKey();

        return [
            'tenant' => [
                'id' => $tenantId,
                'name' => $tenant->getAttribute('name'),
                'status' => $tenant->getAttribute('status') ?? 'active',
                'data' => $tenant->getAttribute('data'),
            ],
            'domains' => $tenant->domains()->pluck('domain')->all(),
            'settings' => DB::table('settings')->where('tenant_id', $tenantId)->get()->map(fn ($row) => (array) $row)->all(),
            'affiliates' => DB::table('affiliates')->where('tenant_id', $tenantId)->get()->map(fn ($row) => (array) $row)->all(),
            'products' => DB::table('lunar_products')->where('tenant_id', $tenantId)->get()->map(fn ($row) => (array) $row)->all(),
            'variants' => DB::table('lunar_product_variants')->where('tenant_id', $tenantId)->get()->map(fn ($row) => (array) $row)->all(),
            'wholesale_prices' => DB::table('wholesale_prices')->where('tenant_id', $tenantId)->get()->map(fn ($row) => (array) $row)->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function import(string $tenantId, array $data): Tenant
    {
        return DB::transaction(function () use ($tenantId, $data) {
            /** @var array{name?: string, status?: string, data?: array<string, mixed>} $tenantMeta */
            $tenantMeta = $data['tenant'] ?? [];

            /** @var Tenant $tenant */
            $tenant = Tenant::firstOrCreate(
                ['id' => $tenantId],
                [
                    'name' => $tenantMeta['name'] ?? $tenantId,
                    'status' => $tenantMeta['status'] ?? 'active',
                ]
            );

            // Import domains
            if (! empty($data['domains']) && is_array($data['domains'])) {
                foreach ($data['domains'] as $domain) {
                    $tenant->domains()->firstOrCreate(['domain' => (string) $domain]);
                }
            }

            // Import settings
            if (! empty($data['settings']) && is_array($data['settings'])) {
                foreach ($data['settings'] as $setting) {
                    if (is_array($setting) && isset($setting['group'], $setting['name'])) {
                        DB::table('settings')->updateOrInsert(
                            ['tenant_id' => $tenantId, 'group' => $setting['group'], 'name' => $setting['name']],
                            ['payload' => $setting['payload'] ?? '{}', 'updated_at' => now(), 'created_at' => now()]
                        );
                    }
                }
            }

            // Import affiliates
            if (! empty($data['affiliates']) && is_array($data['affiliates'])) {
                foreach ($data['affiliates'] as $affiliate) {
                    if (is_array($affiliate) && isset($affiliate['code'])) {
                        DB::table('affiliates')->updateOrInsert(
                            ['tenant_id' => $tenantId, 'code' => $affiliate['code']],
                            [
                                'name' => $affiliate['name'] ?? 'Imported Affiliate',
                                'commission_rate' => $affiliate['commission_rate'] ?? 10,
                                'status' => $affiliate['status'] ?? 'active',
                                'updated_at' => now(),
                                'created_at' => now(),
                            ]
                        );
                    }
                }
            }

            return $tenant;
        });
    }
}
