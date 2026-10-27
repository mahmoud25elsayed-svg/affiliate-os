<?php

declare(strict_types=1);

namespace App\Support\Idempotency;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $key
 * @property string $request_method
 * @property string $request_path
 * @property string $request_hash
 * @property int $response_status
 * @property array<string, mixed>|null $response_headers
 * @property string|null $response_body
 * @property Carbon|null $expires_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class IdempotencyKey extends Model
{
    protected $fillable = [
        'tenant_id',
        'key',
        'request_method',
        'request_path',
        'request_hash',
        'response_status',
        'response_headers',
        'response_body',
        'expires_at',
    ];

    protected $casts = [
        'response_status' => 'integer',
        'response_headers' => 'array',
        'expires_at' => 'datetime',
    ];
}
