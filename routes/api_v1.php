<?php

declare(strict_types=1);

use App\Http\Controllers\Api\OpenApiDocsController;
use App\Http\Controllers\Api\V1\Affiliate\AffiliateCatalogApiController;
use App\Http\Controllers\Api\V1\Affiliate\AffiliateOrderApiController;
use App\Http\Controllers\Api\V1\Affiliate\AffiliateWalletApiController;
use App\Http\Controllers\Api\V1\Store\StoreCustomerApiController;
use App\Http\Controllers\Api\V1\Store\StoreInventoryApiController;
use App\Http\Controllers\Api\V1\Store\StoreOrderApiController;
use App\Http\Controllers\Api\V1\Store\StoreProductApiController;
use App\Http\Middleware\AuthenticateApiKey;
use App\Http\Middleware\RequireApiKeyScope;
use App\Http\Middleware\RequireIdempotencyKey;
use App\Models\ApiKey;
use Illuminate\Support\Facades\Route;

// Public OpenAPI specification & Documentation
Route::get('/v1/openapi.json', [OpenApiDocsController::class, 'schema'])->name('api.v1.openapi.schema');

// Authenticated REST API v1
Route::prefix('v1')->middleware([AuthenticateApiKey::class])->group(function () {

    // Merchant Endpoints
    Route::prefix('store')->group(function () {

        // Products
        Route::get('/products', [StoreProductApiController::class, 'index'])
            ->middleware(RequireApiKeyScope::class.':'.ApiKey::SCOPE_MERCHANT_PRODUCTS_READ)
            ->name('api.v1.store.products.index');

        Route::post('/products', [StoreProductApiController::class, 'store'])
            ->middleware([
                RequireApiKeyScope::class.':'.ApiKey::SCOPE_MERCHANT_PRODUCTS_WRITE,
                RequireIdempotencyKey::class.':strict',
            ])
            ->name('api.v1.store.products.store');

        Route::get('/products/{id}', [StoreProductApiController::class, 'show'])
            ->middleware(RequireApiKeyScope::class.':'.ApiKey::SCOPE_MERCHANT_PRODUCTS_READ)
            ->name('api.v1.store.products.show');

        // Orders
        Route::get('/orders', [StoreOrderApiController::class, 'index'])
            ->middleware(RequireApiKeyScope::class.':'.ApiKey::SCOPE_MERCHANT_ORDERS_READ)
            ->name('api.v1.store.orders.index');

        Route::get('/orders/{id}', [StoreOrderApiController::class, 'show'])
            ->middleware(RequireApiKeyScope::class.':'.ApiKey::SCOPE_MERCHANT_ORDERS_READ)
            ->name('api.v1.store.orders.show');

        Route::post('/orders/{id}/transition', [StoreOrderApiController::class, 'transition'])
            ->middleware([
                RequireApiKeyScope::class.':'.ApiKey::SCOPE_MERCHANT_ORDERS_WRITE,
                RequireIdempotencyKey::class.':strict',
            ])
            ->name('api.v1.store.orders.transition');

        // Inventory
        Route::get('/inventory', [StoreInventoryApiController::class, 'index'])
            ->middleware(RequireApiKeyScope::class.':'.ApiKey::SCOPE_MERCHANT_INVENTORY_READ)
            ->name('api.v1.store.inventory.index');

        Route::post('/inventory/adjust', [StoreInventoryApiController::class, 'adjust'])
            ->middleware([
                RequireApiKeyScope::class.':'.ApiKey::SCOPE_MERCHANT_INVENTORY_WRITE,
                RequireIdempotencyKey::class.':strict',
            ])
            ->name('api.v1.store.inventory.adjust');

        // Customers
        Route::get('/customers', [StoreCustomerApiController::class, 'index'])
            ->middleware(RequireApiKeyScope::class.':'.ApiKey::SCOPE_MERCHANT_CUSTOMERS_READ)
            ->name('api.v1.store.customers.index');
    });

    // Affiliate Endpoints
    Route::prefix('affiliate')->group(function () {

        // Catalog
        Route::get('/catalog', [AffiliateCatalogApiController::class, 'index'])
            ->middleware(RequireApiKeyScope::class.':'.ApiKey::SCOPE_AFFILIATE_CATALOG_READ)
            ->name('api.v1.affiliate.catalog.index');

        Route::get('/catalog/{id}', [AffiliateCatalogApiController::class, 'show'])
            ->middleware(RequireApiKeyScope::class.':'.ApiKey::SCOPE_AFFILIATE_CATALOG_READ)
            ->name('api.v1.affiliate.catalog.show');

        // Orders
        Route::get('/orders', [AffiliateOrderApiController::class, 'index'])
            ->middleware(RequireApiKeyScope::class.':'.ApiKey::SCOPE_AFFILIATE_ORDERS_READ)
            ->name('api.v1.affiliate.orders.index');

        Route::get('/orders/{id}', [AffiliateOrderApiController::class, 'show'])
            ->middleware(RequireApiKeyScope::class.':'.ApiKey::SCOPE_AFFILIATE_ORDERS_READ)
            ->name('api.v1.affiliate.orders.show');

        Route::post('/orders', [AffiliateOrderApiController::class, 'store'])
            ->middleware([
                RequireApiKeyScope::class.':'.ApiKey::SCOPE_AFFILIATE_ORDERS_CREATE,
                RequireIdempotencyKey::class.':strict',
            ])
            ->name('api.v1.affiliate.orders.store');

        // Wallet
        Route::get('/wallet', [AffiliateWalletApiController::class, 'show'])
            ->middleware(RequireApiKeyScope::class.':'.ApiKey::SCOPE_AFFILIATE_WALLET_READ)
            ->name('api.v1.affiliate.wallet.show');
    });
});
