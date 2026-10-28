<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $tenant_id
 * @property string $name_ar
 * @property string $name_en
 * @property string $slug
 * @property int $tier_order
 * @property string $badge_color
 * @property int $min_delivered_orders
 * @property int $min_delivery_rate
 * @property int $min_quality_score
 * @property int $bonus_per_order_minor
 * @property int $commission_bonus_percent
 * @property int $daily_order_limit
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Affiliate> $affiliates
 */
class AffiliateLevel extends Model
{
    use BelongsToTenant;

    public const SLUG_BRONZE = 'bronze';

    public const SLUG_SILVER = 'silver';

    public const SLUG_GOLD = 'gold';

    public const SLUG_PLATINUM = 'platinum';

    protected $table = 'affiliate_levels';

    protected $fillable = [
        'tenant_id',
        'name_ar',
        'name_en',
        'slug',
        'tier_order',
        'badge_color',
        'min_delivered_orders',
        'min_delivery_rate',
        'min_quality_score',
        'bonus_per_order_minor',
        'commission_bonus_percent',
        'daily_order_limit',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tier_order' => 'integer',
            'min_delivered_orders' => 'integer',
            'min_delivery_rate' => 'integer',
            'min_quality_score' => 'integer',
            'bonus_per_order_minor' => 'integer',
            'commission_bonus_percent' => 'integer',
            'daily_order_limit' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<Affiliate, $this>
     */
    public function affiliates(): HasMany
    {
        return $this->hasMany(Affiliate::class, 'affiliate_level_id');
    }

    /**
     * Seeds the 4 default standard tiers for a merchant/tenant.
     *
     * @return Collection<int, AffiliateLevel>
     */
    public static function seedDefaultsForTenant(string $tenantId): Collection
    {
        $defaults = [
            [
                'name_ar' => 'برونزي',
                'name_en' => 'Bronze',
                'slug' => self::SLUG_BRONZE,
                'tier_order' => 1,
                'badge_color' => '#cd7f32',
                'min_delivered_orders' => 0,
                'min_delivery_rate' => 0,
                'min_quality_score' => 0,
                'bonus_per_order_minor' => 0,
                'commission_bonus_percent' => 0,
                'daily_order_limit' => 20,
                'is_active' => true,
            ],
            [
                'name_ar' => 'فضي',
                'name_en' => 'Silver',
                'slug' => self::SLUG_SILVER,
                'tier_order' => 2,
                'badge_color' => '#94a3b8',
                'min_delivered_orders' => 20,
                'min_delivery_rate' => 7000,
                'min_quality_score' => 60,
                'bonus_per_order_minor' => 500, // 5.00 EGP bonus per delivered order
                'commission_bonus_percent' => 2,
                'daily_order_limit' => 50,
                'is_active' => true,
            ],
            [
                'name_ar' => 'ذهبي',
                'name_en' => 'Gold',
                'slug' => self::SLUG_GOLD,
                'tier_order' => 3,
                'badge_color' => '#eab308',
                'min_delivered_orders' => 100,
                'min_delivery_rate' => 8000,
                'min_quality_score' => 75,
                'bonus_per_order_minor' => 1500, // 15.00 EGP bonus per delivered order
                'commission_bonus_percent' => 5,
                'daily_order_limit' => 150,
                'is_active' => true,
            ],
            [
                'name_ar' => 'بلاتيني',
                'name_en' => 'Platinum',
                'slug' => self::SLUG_PLATINUM,
                'tier_order' => 4,
                'badge_color' => '#06b6d4',
                'min_delivered_orders' => 300,
                'min_delivery_rate' => 8800,
                'min_quality_score' => 85,
                'bonus_per_order_minor' => 3000, // 30.00 EGP bonus per delivered order
                'commission_bonus_percent' => 10,
                'daily_order_limit' => 500,
                'is_active' => true,
            ],
        ];

        /** @var Collection<int, AffiliateLevel> $created */
        $created = new Collection;
        foreach ($defaults as $data) {
            /** @var AffiliateLevel $level */
            $level = static::firstOrCreate(
                ['tenant_id' => $tenantId, 'slug' => $data['slug']],
                $data
            );
            $created->push($level);
        }

        return $created;
    }
}
