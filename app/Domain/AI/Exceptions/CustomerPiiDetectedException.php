<?php

declare(strict_types=1);

namespace App\Domain\AI\Exceptions;

use RuntimeException;

class CustomerPiiDetectedException extends RuntimeException
{
    public function __construct(string $detectedType = 'phone or email')
    {
        parent::__construct("Security Exception: Customer personally identifiable information (PII) detected [{$detectedType}]. Customer data is strictly prohibited from being sent to external AI models.");
    }
}
