<?php

declare(strict_types=1);

namespace App\Support\Audit;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string|null $actor_type
 * @property string|null $actor_id
 * @property string $action
 * @property string $subject_type
 * @property string $subject_id
 * @property array<string, mixed>|null $before
 * @property array<string, mixed>|null $after
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property string|null $tenant_id
 * @property Carbon $created_at
 */
class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'actor_type',
        'actor_id',
        'action',
        'subject_type',
        'subject_id',
        'before',
        'after',
        'ip_address',
        'user_agent',
        'tenant_id',
    ];

    protected $casts = [
        'before' => 'array',
        'after' => 'array',
        'created_at' => 'datetime',
    ];
}
