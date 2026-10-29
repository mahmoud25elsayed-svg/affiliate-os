<?php

use App\Models\CustomerReputation;
use App\Models\Domain;
use App\Models\Plan;
use App\Models\PlatformVisit;
use App\Models\Subscription;
use App\Models\SubscriptionEvent;
use App\Models\SubscriptionInvoice;
use App\Models\Tenant;
use App\Models\TenantErrorLog;
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
        Domain::class,
        User::class,
        Plan::class,
        Subscription::class,
        SubscriptionEvent::class,
        SubscriptionInvoice::class,
        CustomerReputation::class,
        TenantErrorLog::class,
        PlatformVisit::class,
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

test('no direct order status mutation outside OrderTransitionService', function () {
    $dirs = [
        app_path('Http'),
        app_path('Livewire'),
        app_path('Filament'),
        app_path('Domain'),
    ];

    foreach ($dirs as $dir) {
        if (! is_dir($dir)) {
            continue;
        }

        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
        foreach ($iterator as $file) {
            /** @var SplFileInfo $file */
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            // Exclude OrderTransitionService itself
            if (str_contains($file->getPathname(), 'OrderTransitionService.php')) {
                continue;
            }

            $content = (string) file_get_contents($file->getPathname());

            // Reject direct status assignments or updates on order variables
            expect($content)->not->toMatch('/\$order->status\s*=[^=]/');
            expect($content)->not->toMatch('/\$order->update\(\[\s*[\'"]status[\'"]\s*=>/');
        }
    }
});
