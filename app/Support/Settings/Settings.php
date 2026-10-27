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
        $tenantId = function_exists('tenancy') && tenancy()->initialized ? (string) tenant('id') : null;
        $cacheKey = $tenantId ? "tenant_{$tenantId}.settings.{$group}" : "settings.{$group}";

        /** @var static $instance */
        $instance = Cache::rememberForever($cacheKey, function () use ($group, $tenantId) {
            $settings = new static;
            $query = DB::table('settings')->where('group', $group);

            if ($tenantId !== null) {
                $query->where('tenant_id', $tenantId);
            } else {
                $query->whereNull('tenant_id');
            }

            $records = $query->get();

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
        $tenantId = function_exists('tenancy') && tenancy()->initialized ? (string) tenant('id') : null;
        $cacheKey = $tenantId ? "tenant_{$tenantId}.settings.{$group}" : "settings.{$group}";

        $ref = new ReflectionClass($this);
        $properties = $ref->getProperties(ReflectionProperty::IS_PUBLIC);

        foreach ($properties as $prop) {
            $name = $prop->getName();
            $value = $prop->getValue($this);

            DB::table('settings')->updateOrInsert(
                ['tenant_id' => $tenantId, 'group' => $group, 'name' => $name],
                [
                    'payload' => json_encode($value),
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }

        Cache::forget($cacheKey);
    }
}
