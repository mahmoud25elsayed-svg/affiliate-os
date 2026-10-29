<?php

declare(strict_types=1);

namespace App\Support\Security\Exceptions;

use Exception;

class SsrfBlockedException extends Exception
{
    public static function blocked(string $url, string $reason): self
    {
        return new self("تم حظر الرابط لأسباب أمنية (SSRF): [{$reason}] - {$url}");
    }
}
