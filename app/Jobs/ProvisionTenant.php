<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Location\Country;
use App\Support\Location\Market;
use App\Support\Settings\GeneralSettings;
use App\Support\Settings\ShippingSettings;
use App\Support\Tenancy\Permissions\TenantPermissionMatrix;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Lunar\Models\Channel;
use Lunar\Models\Currency;
use Lunar\Models\CustomerGroup;
use Lunar\Models\Language;
use Lunar\Models\TaxClass;

class ProvisionTenant implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @param  array{user_id?: int, name?: string, email?: string, password?: string, phone?: string}  $ownerData
     * @param  string  $country  ISO code of the store's home country (config/countries.php)
     */
    public function __construct(
        public string $tenantId,
        public string $name,
        public string $domain,
        public array $ownerData = [],
        public string $country = Market::DEFAULT_COUNTRY,
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

        // 3. Create initial trial subscription (central platform)
        if (! $tenant->subscriptions()->exists()) {
            /** @var Plan|null $starterPlan */
            $starterPlan = Plan::where('slug', 'starter')->first();
            if ($starterPlan) {
                /** @var Subscription $subscription */
                $subscription = $tenant->subscriptions()->create([
                    'plan_id' => $starterPlan->id,
                    'status' => Subscription::STATUS_TRIAL,
                    'billing_cycle' => Subscription::CYCLE_MONTHLY,
                    'trial_ends_at' => now()->addDays($starterPlan->trial_days),
                    'current_period_starts_at' => now(),
                    'current_period_ends_at' => now()->addDays($starterPlan->trial_days),
                ]);

                $subscription->events()->create([
                    'tenant_id' => $tenant->id,
                    'event' => 'trial_started',
                    'metadata' => [
                        'plan' => $starterPlan->slug,
                        'trial_days' => $starterPlan->trial_days,
                    ],
                    'created_at' => now(),
                ]);
            }
        }

        // 3. Initialize tenant and run seeders
        $tenant->run(function () use ($tenant) {
            // Seed RBAC matrix for this tenant
            TenantPermissionMatrix::seedForTenant((string) $tenant->id);

            // Store currency follows its home country (amounts are kept in hundredths, see Money)
            $country = Country::find($this->country) ?? Country::get(Market::DEFAULT_COUNTRY);
            Currency::firstOrCreate(
                ['code' => $country->currency],
                [
                    'name' => (string) config("currencies.{$country->currency}.name_en", $country->currency),
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
            $settings->country_code = $country->code;
            $settings->default_currency = $country->currency;
            $settings->default_locale = $country->arab ? 'ar' : 'en';
            $settings->save();

            // Starting shipping fees sized for the store's currency (whole units -> hundredths).
            /** @var array{0: int, 1: int, 2: int}|null $shippingDefaults */
            $shippingDefaults = config("currencies.{$country->currency}.shipping");
            if (is_array($shippingDefaults)) {
                $shipping = ShippingSettings::load();
                $shipping->default_rate_minor = $shippingDefaults[0] * 100;
                $shipping->cod_fee_minor = $shippingDefaults[1] * 100;
                $shipping->free_shipping_threshold_minor = $shippingDefaults[2] * 100;
                $shipping->save();
            }

            // Create or update Owner User
            if (! empty($this->ownerData['email']) || ! empty($this->ownerData['user_id'])) {
                /** @var User|null $ownerUser */
                $ownerUser = ! empty($this->ownerData['user_id'])
                    ? User::find($this->ownerData['user_id'])
                    : User::firstWhere('email', $this->ownerData['email'] ?? '');

                if (! $ownerUser && ! empty($this->ownerData['email'])) {
                    $passwordHash = Hash::make($this->ownerData['password'] ?? Str::random(32));

                    $ownerUser = User::create([
                        'email' => $this->ownerData['email'],
                        'name' => $this->ownerData['name'] ?? 'Merchant Owner',
                        'password' => $passwordHash,
                        'phone' => $this->ownerData['phone'] ?? null,
                        'phone_verified_at' => now(),
                        'tenant_id' => $tenant->id,
                    ]);
                }

                // Never grant ownership of this store to an account that belongs to a different tenant.
                if ($ownerUser && ($ownerUser->tenant_id === null || (string) $ownerUser->tenant_id === (string) $tenant->id)) {
                    TenantPermissionMatrix::assignRole($ownerUser, TenantPermissionMatrix::ROLE_OWNER, (string) $tenant->id);
                }
            }
        });

        return $tenant;
    }
}
