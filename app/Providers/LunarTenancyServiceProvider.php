<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Tenant;
use App\Support\Tenancy\Exceptions\TenantCannotBeNullException;
use App\Support\Tenancy\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;
use Lunar\Models\Address;
use Lunar\Models\Brand;
use Lunar\Models\Cart;
use Lunar\Models\CartAddress;
use Lunar\Models\CartLine;
use Lunar\Models\Channel;
use Lunar\Models\Collection;
use Lunar\Models\CollectionGroup;
use Lunar\Models\Currency;
use Lunar\Models\Customer;
use Lunar\Models\CustomerGroup;
use Lunar\Models\Discount;
use Lunar\Models\Order;
use Lunar\Models\OrderAddress;
use Lunar\Models\OrderLine;
use Lunar\Models\Price;
use Lunar\Models\Product;
use Lunar\Models\ProductOption;
use Lunar\Models\ProductOptionValue;
use Lunar\Models\ProductType;
use Lunar\Models\ProductVariant;
use Lunar\Models\TaxClass;
use Lunar\Models\TaxRate;
use Lunar\Models\TaxZone;
use Lunar\Models\Transaction;

class LunarTenancyServiceProvider extends ServiceProvider
{
    /**
     * @var list<class-string<Model>>
     */
    protected array $lunarModels = [
        Product::class,
        ProductVariant::class,
        ProductOption::class,
        ProductOptionValue::class,
        ProductType::class,
        Price::class,
        Collection::class,
        CollectionGroup::class,
        Brand::class,
        Order::class,
        OrderLine::class,
        OrderAddress::class,
        Cart::class,
        CartLine::class,
        CartAddress::class,
        Customer::class,
        CustomerGroup::class,
        Address::class,
        Channel::class,
        Currency::class,
        Discount::class,
        Transaction::class,
        TaxClass::class,
        TaxZone::class,
        TaxRate::class,
    ];

    public function boot(): void
    {
        $tenantScope = new TenantScope;

        foreach ($this->lunarModels as $modelClass) {
            $modelClass::addGlobalScope($tenantScope);

            // The Store panel uses Filament tenancy, which scopes and associates records through a
            // `tenant` relationship; Lunar models don't ship one.
            $modelClass::resolveRelationUsing('tenant', fn (Model $model) => $model->belongsTo(Tenant::class, 'tenant_id'));

            $modelClass::saving(function (Model $model): void {
                if (! $model->getAttribute('tenant_id') && function_exists('tenancy') && tenancy()->initialized) {
                    $model->setAttribute('tenant_id', tenant('id'));
                }

                if (empty($model->getAttribute('tenant_id'))) {
                    throw TenantCannotBeNullException::forModel($model::class);
                }
            });
        }
    }
}
