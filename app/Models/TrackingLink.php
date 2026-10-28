<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Lunar\Models\Order;
use Lunar\Models\Product;
use Lunar\Models\ProductVariant;

class TrackingLink extends Model
{
    use BelongsToTenant;
    use SoftDeletes;

    protected $table = 'tracking_links';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'affiliate_id',
        'product_id',
        'product_variant_id',
        'code',
        'title',
        'custom_selling_price_minor',
        'commission_rate_percent',
        'clicks_count',
        'orders_count',
        'is_active',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'custom_selling_price_minor' => 'integer',
        'commission_rate_percent' => 'float',
        'clicks_count' => 'integer',
        'orders_count' => 'integer',
        'is_active' => 'boolean',
    ];

    /**
     * @return BelongsTo<Affiliate, $this>
     */
    public function affiliate(): BelongsTo
    {
        return $this->belongsTo(Affiliate::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    /**
     * @return BelongsTo<ProductVariant, $this>
     */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    /**
     * @return HasMany<AffiliateClick, $this>
     */
    public function clicks(): HasMany
    {
        return $this->hasMany(AffiliateClick::class, 'tracking_link_id');
    }

    /**
     * @return HasMany<Order, $this>
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'tracking_link_id');
    }

    /**
     * Returns full tracking URL.
     */
    public function getUrl(): string
    {
        if (function_exists('route')) {
            try {
                return route('storefront.ref', ['code' => $this->code]);
            } catch (\Throwable) {
                // fallback
            }
        }

        return url('/ref/'.$this->code);
    }

    /**
     * Generates a lightweight standalone SVG QR Code representation.
     */
    public function getQrCodeSvg(): string
    {
        $url = htmlspecialchars($this->getUrl(), ENT_QUOTES, 'UTF-8');

        // Clean SVG placeholder box with link text & quick visual representation
        return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 200 200" width="160" height="160" class="mx-auto rounded border border-gray-200 bg-white p-2">
    <rect width="100%" height="100%" fill="#ffffff"/>
    <!-- QR Corner Markers -->
    <rect x="20" y="20" width="40" height="40" fill="#1e293b" rx="4"/>
    <rect x="28" y="28" width="24" height="24" fill="#ffffff" rx="2"/>
    <rect x="34" y="34" width="12" height="12" fill="#1e293b"/>

    <rect x="140" y="20" width="40" height="40" fill="#1e293b" rx="4"/>
    <rect x="148" y="28" width="24" height="24" fill="#ffffff" rx="2"/>
    <rect x="154" y="34" width="12" height="12" fill="#1e293b"/>

    <rect x="20" y="140" width="40" height="40" fill="#1e293b" rx="4"/>
    <rect x="28" y="148" width="24" height="24" fill="#ffffff" rx="2"/>
    <rect x="34" y="154" width="12" height="12" fill="#1e293b"/>

    <!-- Decorative QR Data Points -->
    <rect x="80" y="25" width="12" height="12" fill="#3b82f6"/>
    <rect x="105" y="35" width="12" height="12" fill="#1e293b"/>
    <rect x="75" y="75" width="16" height="16" fill="#1e293b"/>
    <rect x="105" y="75" width="16" height="16" fill="#3b82f6"/>
    <rect x="140" y="85" width="12" height="12" fill="#1e293b"/>
    <rect x="30" y="95" width="12" height="12" fill="#1e293b"/>
    <rect x="85" y="115" width="14" height="14" fill="#1e293b"/>
    <rect x="110" y="115" width="14" height="14" fill="#1e293b"/>
    <rect x="145" y="145" width="30" height="30" fill="#1e293b" rx="2"/>
    <rect x="80" y="150" width="16" height="16" fill="#3b82f6"/>

    <text x="100" y="192" font-size="9" text-anchor="middle" fill="#64748b" font-family="sans-serif">{$this->code}</text>
</svg>
SVG;
    }
}
