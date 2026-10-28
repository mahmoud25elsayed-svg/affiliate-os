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
 * @property string $tenant_id
 * @property int|null $user_id
 * @property int|null $affiliate_id
 * @property string $provider
 * @property string $model
 * @property string $action
 * @property int $prompt_tokens
 * @property int $completion_tokens
 * @property int $total_tokens
 * @property int $estimated_cost_cents
 * @property string $status
 * @property string|null $error_message
 * @property CarbonInterface $created_at
 * @property CarbonInterface $updated_at
 * @property-read Tenant $tenant
 * @property-read User|null $user
 * @property-read Affiliate|null $affiliate
 */
class AiUsageLog extends Model
{
    use BelongsToTenant;

    public const STATUS_SUCCESS = 'success';

    public const STATUS_FAILED = 'failed';

    public const ACTION_PRODUCT_COPY = 'product_copy';

    public const ACTION_SEO_META = 'seo_meta';

    public const ACTION_CATEGORY_TAGS = 'category_tags';

    public const ACTION_TRANSLATE = 'translate';

    public const ACTION_AD_COPY = 'ad_copy';

    public const ACTION_MARKETING_ANGLES = 'marketing_angles';

    public const ACTION_POLICY_CHECK = 'policy_check';

    protected $table = 'ai_usage_logs';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'user_id',
        'affiliate_id',
        'provider',
        'model',
        'action',
        'prompt_tokens',
        'completion_tokens',
        'total_tokens',
        'estimated_cost_cents',
        'status',
        'error_message',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'prompt_tokens' => 'integer',
            'completion_tokens' => 'integer',
            'total_tokens' => 'integer',
            'estimated_cost_cents' => 'integer',
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
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Affiliate, $this>
     */
    public function affiliate(): BelongsTo
    {
        return $this->belongsTo(Affiliate::class);
    }

    /**
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeCurrentMonth(Builder $query): Builder
    {
        return $query->where('created_at', '>=', now()->startOfMonth());
    }

    /**
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeSuccessful(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_SUCCESS);
    }
}
