<?php

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SubscriptionEvent;
use App\Models\SubscriptionInvoice;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy\Concerns\BelongsToTenant;

arch('globals')
    ->expect(['dd', 'dump', 'ray'])
    ->not->toBeUsed();

arch('finance and orders domains do not use floatval')
    ->expect(['App\Domain\Finance', 'App\Domain\Orders'])
    ->not->toUse(['floatval']);

test('all tenant models use BelongsToTenant trait', function () {
    $platformModels = [
        Tenant::class,
        User::class,
        Plan::class,
        Subscription::class,
        SubscriptionEvent::class,
        SubscriptionInvoice::class,
    ];

    $modelFiles = glob(app_path('Models/*.php')) ?: [];
    foreach ($modelFiles as $file) {
        $className = 'App\\Models\\'.basename($file, '.php');
        if (in_array($className, $platformModels, true)) {
            continue;
        }

        $traits = class_uses_recursive($className);
        expect($traits)->toHaveKey(BelongsToTenant::class);
    }
});
