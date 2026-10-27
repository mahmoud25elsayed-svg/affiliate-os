<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $tenant_id
 * @property string $title_ar
 * @property string|null $title_en
 * @property string $slug
 * @property Carbon $starts_at
 * @property Carbon $ends_at
 * @property bool $is_active
 * @property string|null $banner_url
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, FlashSaleItem> $items
 */
class FlashSale extends Model
{
    use BelongsToTenant;

    protected $table = 'flash_sales';

    protected $fillable = [
        'tenant_id',
        'title_ar',
        'title_en',
        'name',
        'slug',
        'starts_at',
        'ends_at',
        'is_active',
        'banner_url',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $sale): void {
            if (empty($sale->title_ar) && ! empty($sale->title_en)) {
                $sale->title_ar = $sale->title_en;
            }
            if (empty($sale->slug)) {
                $base = ! empty($sale->title_ar) ? $sale->title_ar : 'flash-sale';
                $sale->slug = Str::slug($base).'-'.Str::random(6);
            }
        });
    }

    public function getNameAttribute(): string
    {
        return $this->title_ar ?? '';
    }

    public function setNameAttribute(string $value): void
    {
        $this->attributes['title_ar'] = $value;
    }

    /**
     * @return HasMany<FlashSaleItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(FlashSaleItem::class, 'flash_sale_id');
    }

    /**
     * Scope for currently running and active flash sales.
     *
     * @param  Builder<FlashSale>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $now = now();
        $query->where('is_active', true)
            ->where('starts_at', '<=', $now)
            ->where('ends_at', '>', $now);
    }

    /**
     * Check if flash sale is currently active and within time window.
     */
    public function isOngoing(): bool
    {
        $now = now();

        return $this->is_active && $this->starts_at->lte($now) && $this->ends_at->gt($now);
    }

    /**
     * Remaining duration in seconds until flash sale ends.
     */
    public function timeRemainingInSeconds(): int
    {
        if (! $this->isOngoing()) {
            return 0;
        }

        return max(0, (int) now()->diffInSeconds($this->ends_at, false));
    }
}
