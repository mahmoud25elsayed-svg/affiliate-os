<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $path
 * @property string|null $referrer_domain
 * @property string $referrer_type
 * @property string $device_category
 * @property string $country_code
 * @property string $visitor_hash
 * @property bool $converted
 * @property string|null $conversion_type
 * @property Carbon $visited_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class PlatformVisit extends Model
{
    protected $table = 'platform_visits';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'path',
        'referrer_domain',
        'referrer_type',
        'device_category',
        'country_code',
        'visitor_hash',
        'converted',
        'conversion_type',
        'visited_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'converted' => 'boolean',
            'visited_at' => 'datetime',
        ];
    }
}
