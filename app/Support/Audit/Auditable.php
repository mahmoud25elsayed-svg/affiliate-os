<?php

declare(strict_types=1);

namespace App\Support\Audit;

use Illuminate\Database\Eloquent\Model;

trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(function (Model $model): void {
            /** @var array<string, mixed> $attributes */
            $attributes = $model->getAttributes();
            AuditLogger::log('created', $model, null, $attributes);
        });

        static::updated(function (Model $model): void {
            /** @var array<string, mixed> $before */
            $before = array_intersect_key($model->getOriginal(), $model->getDirty());
            /** @var array<string, mixed> $after */
            $after = $model->getDirty();

            if (! empty($after)) {
                AuditLogger::log('updated', $model, $before, $after);
            }
        });

        static::deleted(function (Model $model): void {
            /** @var array<string, mixed> $attributes */
            $attributes = $model->getAttributes();
            AuditLogger::log('deleted', $model, $attributes, null);
        });
    }
}
