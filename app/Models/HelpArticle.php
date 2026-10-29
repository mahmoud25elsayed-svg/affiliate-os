<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Tenancy\Concerns\BelongsToTenant;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string|null $tenant_id
 * @property string $category
 * @property string $audience
 * @property string $question
 * @property string|null $slug
 * @property string $answer
 * @property int $sort_order
 * @property bool $is_published
 * @property CarbonInterface $created_at
 * @property CarbonInterface $updated_at
 * @property-read Tenant|null $tenant
 */
class HelpArticle extends Model
{
    use BelongsToTenant;

    public const AUDIENCE_PUBLIC = 'public';

    public const AUDIENCE_AFFILIATES = 'affiliates';

    public const AUDIENCE_MERCHANTS = 'merchants';

    protected $table = 'help_articles';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'category',
        'audience',
        'question',
        'slug',
        'answer',
        'sort_order',
        'is_published',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_published' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (HelpArticle $article) {
            if (empty($article->slug)) {
                $article->slug = Str::slug($article->question).'-'.substr(uniqid(), -4);
            }
        });
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    /**
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true)->orderBy('sort_order');
    }

    /**
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeForAudience(Builder $query, string $audience): Builder
    {
        return $query->where('audience', $audience);
    }

    /**
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeByCategory(Builder $query, string $category): Builder
    {
        return $query->where('category', $category);
    }
}
