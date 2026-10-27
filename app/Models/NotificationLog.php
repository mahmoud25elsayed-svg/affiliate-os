<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $tenant_id
 * @property string $channel
 * @property string $provider
 * @property string $recipient
 * @property string $event_type
 * @property string|null $subject
 * @property string $content
 * @property string $status
 * @property string|null $error_message
 * @property string|null $external_id
 * @property int $cost_minor
 * @property int $retry_count
 * @property Carbon|null $sent_at
 * @property string|null $notifiable_type
 * @property int|null $notifiable_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Tenant|null $tenant
 * @property-read Model|null $notifiable
 */
class NotificationLog extends Model
{
    use BelongsToTenant;

    protected $table = 'notification_logs';

    protected $fillable = [
        'tenant_id',
        'channel',
        'provider',
        'recipient',
        'event_type',
        'subject',
        'content',
        'status',
        'error_message',
        'external_id',
        'cost_minor',
        'retry_count',
        'sent_at',
        'notifiable_type',
        'notifiable_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cost_minor' => 'integer',
            'retry_count' => 'integer',
            'sent_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function notifiable(): MorphTo
    {
        return $this->morphTo();
    }
}
