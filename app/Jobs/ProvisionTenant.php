<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Tenant;
use App\Models\User;
use App\Support\Settings\GeneralSettings;
use App\Support\Tenancy\Permissions\TenantPermissionMatrix;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Hash;
use Lunar\Models\Channel;
use Lunar\Models\Currency;
use Lunar\Models\CustomerGroup;
use Lunar\Models\Language;
use Lunar\Models\TaxClass;

class ProvisionTenant implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @param  array{name?: string, email?: string, password?: string, phone?: string}  $ownerData
     */
    public function __construct(
        public string $tenantId,
        public string $name,
        public string $domain,
        public array $ownerData = []
    ) {}

    public function handle(): Tenant
    {
        // 1. Create or retrieve Tenant (Idempotent)
        /** @var Tenant $tenant */
        $tenant = Tenant::firstOrCreate(
            ['id' => $this->tenantId],
            [
                'name' => $this->name,
                'status' => 'active',
            ]
        );

        if ($tenant->status === 'provisioning') {
            $tenant->update(['status' => 'active']);
        }

        // 2. Attach domain (Idempotent)
        $tenant->domains()->firstOrCreate(
            ['domain' => $this->domain]
        );

        // 3. Initialize tenant and run seeders
        $tenant->run(function () use ($tenant) {
            // Seed RBAC matrix for this tenant
            TenantPermissionMatrix::seedForTenant((string) $tenant->id);

            // Default Currency: EGP
            Currency::firstOrCreate(
                ['code' => 'EGP'],
                [
                    'name' => 'Egyptian Pound',
                    'exchange_rate' => 1.0,
                    'decimal_places' => 2,
                    'enabled' => true,
                    'default' => true,
                ]
            );

            // Default Languages: Arabic (default) & English
            Language::firstOrCreate(
                ['code' => 'ar'],
                [
                    'name' => 'Arabic',
                    'default' => true,
                ]
            );

            Language::firstOrCreate(
                ['code' => 'en'],
                [
                    'name' => 'English',
                    'default' => false,
                ]
            );

            // Default Channel
            Channel::firstOrCreate(
                ['handle' => 'default'],
                [
                    'name' => $this->name.' Storefront',
                    'default' => true,
                    'url' => 'https://'.$this->domain,
                ]
            );

            // Default Customer Group
            CustomerGroup::firstOrCreate(
                ['handle' => 'default'],
                [
                    'name' => 'Default Retail Customers',
                    'default' => true,
                ]
            );

            // Default Tax Class
            TaxClass::firstOrCreate(
                ['name' => 'Standard Rate'],
                [
                    'default' => true,
                ]
            );

            // Seed initial General Settings
            $settings = GeneralSettings::load();
            $settings->platform_name = $this->name;
            $settings->save();

            // Create or update Owner User
            if (! empty($this->ownerData['email'])) {
                /** @var User $ownerUser */
                $ownerUser = User::firstOrCreate(
                    ['email' => $this->ownerData['email']],
                    [
                        'name' => $this->ownerData['name'] ?? 'Merchant Owner',
                        'password' => Hash::make($this->ownerData['password'] ?? 'secret123'),
                        'phone' => $this->ownerData['phone'] ?? null,
                        'phone_verified_at' => now(),
                        'tenant_id' => $tenant->id,
                    ]
                );

                TenantPermissionMatrix::assignRole($ownerUser, TenantPermissionMatrix::ROLE_OWNER, (string) $tenant->id);
            }
        });

        return $tenant;
    }
}
