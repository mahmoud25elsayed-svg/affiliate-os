<?php

declare(strict_types=1);

namespace App\Domain\Finance\Exceptions;

use RuntimeException;

class ImmutableFinancialRecordException extends RuntimeException
{
    public function __construct(string $message = 'القيود المالية والسطور الدفترية غير قابلة للتعديل أو الحذف بتاتاً.')
    {
        parent::__construct($message);
    }
}
