<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Notifications\Services\TemplateEngine;
use App\Support\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $tenant_id
 * @property string $event_type
 * @property string $channel
 * @property string $locale
 * @property string|null $title
 * @property string|null $subject
 * @property string $content
 * @property array<string, string>|null $variables
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Tenant|null $tenant
 */
class NotificationTemplate extends Model
{
    use BelongsToTenant;

    protected $table = 'notification_templates';

    protected $fillable = [
        'tenant_id',
        'event_type',
        'channel',
        'locale',
        'title',
        'subject',
        'content',
        'variables',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'variables' => 'array',
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
     * @param  array<string, mixed>  $data
     */
    public function render(array $data): string
    {
        return app(TemplateEngine::class)->interpolate($this->content, $data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function renderSubject(array $data): ?string
    {
        if ($this->subject === null) {
            return null;
        }

        return app(TemplateEngine::class)->interpolate($this->subject, $data);
    }
}
