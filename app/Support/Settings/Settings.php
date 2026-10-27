<?php

declare(strict_types=1);

namespace App\Support\Settings;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use ReflectionClass;
use ReflectionProperty;

/**
 * @phpstan-consistent-constructor
 */
abstract class Settings
{
    public function __construct() {}

    abstract public static function group(): string;

    public static function load(): static
    {
        $group = static::group();
        $cacheKey = "settings.{$group}";

        /** @var static $instance */
        $instance = Cache::rememberForever($cacheKey, function () use ($group) {
            $settings = new static;
            $records = DB::table('settings')->where('group', $group)->get();

            foreach ($records as $record) {
                if (property_exists($settings, $record->name)) {
                    $settings->{$record->name} = json_decode($record->payload, true);
                }
            }

            return $settings;
        });

        return $instance;
    }

    public function save(): void
    {
        $group = static::group();
        $ref = new ReflectionClass($this);
        $properties = $ref->getProperties(ReflectionProperty::IS_PUBLIC);

        foreach ($properties as $prop) {
            $name = $prop->getName();
            $value = $prop->getValue($this);

            DB::table('settings')->updateOrInsert(
                ['group' => $group, 'name' => $name],
                [
                    'payload' => json_encode($value),
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }

        Cache::forget("settings.{$group}");
    }
}
