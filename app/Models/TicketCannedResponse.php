<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Tenancy\Concerns\BelongsToTenant;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string|null $tenant_id
 * @property string $title
 * @property string $shortcut
 * @property string $content
 * @property string|null $department
 * @property bool $is_active
 * @property CarbonInterface $created_at
 * @property CarbonInterface $updated_at
 * @property-read Tenant|null $tenant
 */
class TicketCannedResponse extends Model
{
    use BelongsToTenant;

    protected $table = 'ticket_canned_responses';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'title',
        'shortcut',
        'content',
        'department',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
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
     * Replace template placeholders with real values.
     *
     * @param  array<string, string>  $variables
     */
    public function render(array $variables = []): string
    {
        $text = $this->content;
        foreach ($variables as $key => $val) {
            $text = str_replace("{{{$key}}}", $val, $text);
        }

        return $text;
    }

    /**
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
