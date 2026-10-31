<?php

declare(strict_types=1);

namespace App\Support\Location;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * First-level region (governorate / province / state / emirate) of a supported country.
 *
 * The country is encoded in the code: "SA-RIY", "AE-DXB"… Egyptian rows predate
 * multi-country support and keep their bare codes ("CAI"), so a code without "-" is Egypt.
 * No schema change was needed, which keeps deploys migration-free.
 *
 * @property int $id
 * @property string $code
 * @property string $name_ar
 * @property string $name_en
 * @property bool $is_active
 * @property-read string $country_code
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Governorate extends Model
{
    protected $fillable = [
        'code',
        'name_ar',
        'name_en',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * @return HasMany<City, $this>
     */
    public function cities(): HasMany
    {
        return $this->hasMany(City::class);
    }

    public function getCountryCodeAttribute(): string
    {
        return str_contains($this->code, '-') ? strtoupper(explode('-', $this->code, 2)[0]) : 'EG';
    }

    public function name(?string $locale = null): string
    {
        return ($locale ?? app()->getLocale()) === 'en' ? $this->name_en : $this->name_ar;
    }

    /**
     * Name for select lists: adds the country when the store ships to several countries.
     */
    public function label(): string
    {
        if (count(Market::shippingCountries()) <= 1) {
            return $this->name();
        }

        return $this->name().' — '.(Country::find($this->country_code)?->name() ?? $this->country_code);
    }

    /**
     * @param  Builder<Governorate>  $query
     * @param  list<string>  $countries
     */
    public function scopeInCountries(Builder $query, array $countries): void
    {
        $query->where(function (Builder $q) use ($countries): void {
            foreach ($countries as $code) {
                $code === 'EG'
                    ? $q->orWhere('code', 'not like', '%-%')
                    : $q->orWhere('code', 'like', strtoupper($code).'-%');
            }
        });
    }

    /**
     * Active regions of the given countries (the current store's shipping countries by default),
     * seeding a country's regions on first use. Ordered by country, then localized name.
     *
     * @param  list<string>|null  $countries
     * @return Collection<int, Governorate>
     */
    public static function forCountries(?array $countries = null): Collection
    {
        $countries ??= Market::shippingCountries();
        foreach ($countries as $code) {
            self::ensureSeeded($code);
        }

        $nameColumn = app()->getLocale() === 'en' ? 'name_en' : 'name_ar';

        return self::query()->inCountries($countries)->where('is_active', true)->orderBy($nameColumn)->get()
            ->sortBy(fn (Governorate $g): int => (int) array_search($g->country_code, $countries, true))
            ->values();
    }

    /**
     * Insert the regions of a country from database/data/regions.php if it has none yet.
     */
    public static function ensureSeeded(string $country): void
    {
        $country = strtoupper($country);
        if ($country === 'EG' || self::query()->inCountries([$country])->exists()) {
            return;
        }

        /** @var array<string, list<array{0: string, 1: string, 2: string}>> $data */
        $data = require database_path('data/regions.php');

        foreach ($data[$country] ?? [] as [$code, $nameAr, $nameEn]) {
            self::query()->firstOrCreate(
                ['code' => "{$country}-{$code}"],
                ['name_ar' => $nameAr, 'name_en' => $nameEn, 'is_active' => true]
            );
        }
    }
}
