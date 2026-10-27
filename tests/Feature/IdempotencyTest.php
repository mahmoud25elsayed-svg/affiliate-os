<?php

declare(strict_types=1);

use App\Http\Middleware\RequireIdempotencyKey;
use App\Support\Idempotency\IdempotencyKey;
use App\Support\Idempotency\IdempotencyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

beforeEach(function () {
    Route::post('/test-idempotent-endpoint', function () {
        return response()->json([
            'order_id' => rand(1000, 9999),
            'processed_at' => microtime(true),
        ]);
    })->middleware(RequireIdempotencyKey::class);
});

test('idempotent API requests return cached response and header on duplicate key', function () {
    $key = 'test-key-'.uniqid();

    // First request
    $response1 = $this->postJson('/test-idempotent-endpoint', ['item' => 'laptop'], [
        'Idempotency-Key' => $key,
    ]);

    $response1->assertStatus(200);
    $response1->assertHeaderMissing('X-Idempotency-Replay');
    $data1 = $response1->json();

    // Second request with exact same key
    $response2 = $this->postJson('/test-idempotent-endpoint', ['item' => 'laptop'], [
        'Idempotency-Key' => $key,
    ]);

    $response2->assertStatus(200);
    $response2->assertHeader('X-Idempotency-Replay', 'true');
    $data2 = $response2->json();

    // Responses are identical (action was executed only once)
    expect($data1['order_id'])->toBe($data2['order_id'])
        ->and($data1['processed_at'])->toBe($data2['processed_at']);

    // Exactly one idempotency record stored
    expect(IdempotencyKey::where('key', $key)->count())->toBe(1);
});

test('IdempotencyService::run executes callback only once for the same key', function () {
    $key = 'service-key-'.uniqid();
    $counter = 0;

    $result1 = IdempotencyService::run($key, function () use (&$counter) {
        $counter++;

        return ['invoice_number' => 'INV-001', 'exec_count' => $counter];
    });

    expect($result1['exec_count'])->toBe(1);

    // Call again with same key
    $result2 = IdempotencyService::run($key, function () use (&$counter) {
        $counter++;

        return ['invoice_number' => 'INV-002', 'exec_count' => $counter];
    });

    expect($result2['exec_count'])->toBe(1)
        ->and($result2['invoice_number'])->toBe('INV-001')
        ->and($counter)->toBe(1);
});
