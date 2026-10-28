<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $tenant_id
 * @property string $title
 * @property string $slug
 * @property string $content
 * @property string|null $meta_title
 * @property string|null $meta_description
 * @property bool $is_published
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class CustomPage extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    protected $casts = [
        'is_published' => 'boolean',
    ];

    public static function slugify(string $title): string
    {
        $slug = Str::slug($title);

        return ! empty($slug) ? $slug : 'page-'.Str::random(6);
    }
}
