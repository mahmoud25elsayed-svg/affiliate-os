<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string|null $tenant_id
 * @property string $level
 * @property string $message
 * @property array<string, mixed>|null $context
 * @property string|null $exception_class
 * @property Carbon $created_at
 * @property-read Tenant|null $tenant
 */
class TenantErrorLog extends Model
{
    public $timestamps = false;

    protected $table = 'tenant_error_logs';

    protected $fillable = [
        'tenant_id',
        'level',
        'message',
        'context',
        'exception_class',
        'created_at',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'context' => 'array',
        'created_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }
}
