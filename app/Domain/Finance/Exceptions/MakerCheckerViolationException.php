<?php

declare(strict_types=1);

namespace App\Domain\Finance\Exceptions;

use RuntimeException;

class MakerCheckerViolationException extends RuntimeException
{
    public function __construct(string $message = 'لا يمكن لنفس الموظف الذي اعتمد طلب السحب أن يقوم بتنفيذه (قيد Maker-Checker)')
    {
        parent::__construct($message);
    }
}
