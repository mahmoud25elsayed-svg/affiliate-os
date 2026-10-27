<?php

declare(strict_types=1);

use App\Http\Controllers\Api\PaymentWebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

Route::post('/webhooks/payments/{gateway}', [PaymentWebhookController::class, 'handle'])
    ->name('api.webhooks.payments');
