<?php

declare(strict_types=1);

namespace App\Support\Tenancy\Exceptions;

use RuntimeException;

class TenantCannotBeNullException extends RuntimeException
{
    public static function forModel(string $modelClass): self
    {
        return new self("Saving model [{$modelClass}] without a valid tenant_id is prohibited.");
    }
}
