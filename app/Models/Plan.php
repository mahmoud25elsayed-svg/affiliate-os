<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $slug
 * @property string $name_ar
 * @property string $name_en
 * @property string|null $description_ar
 * @property string|null $description_en
 * @property int $price_monthly
 * @property int $price_yearly
 * @property int $trial_days
 * @property array<string, int> $limits
 * @property array<string, bool> $features
 * @property bool $is_active
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Plan extends Model
{
    protected $fillable = [
        'slug',
        'name_ar',
        'name_en',
        'description_ar',
        'description_en',
        'price_monthly',
        'price_yearly',
        'trial_days',
        'limits',
        'features',
        'is_active',
        'sort_order',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'price_monthly' => 'integer',
        'price_yearly' => 'integer',
        'trial_days' => 'integer',
        'limits' => 'array',
        'features' => 'array',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    /**
     * @return HasMany<Subscription, $this>
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function getNameAttribute(): string
    {
        return app()->getLocale() === 'en' ? $this->name_en : $this->name_ar;
    }

    public function getDescriptionAttribute(): ?string
    {
        return app()->getLocale() === 'en' ? $this->description_en : $this->description_ar;
    }

    public function priceMonthlyMoney(): Money
    {
        return Money::fromMinor($this->price_monthly);
    }

    public function priceYearlyMoney(): Money
    {
        return Money::fromMinor($this->price_yearly);
    }

    public function hasFeature(string $feature): bool
    {
        return (bool) ($this->features[$feature] ?? false);
    }

    public function getLimit(string $limit): int
    {
        return isset($this->limits[$limit]) ? (int) $this->limits[$limit] : 0;
    }
}
