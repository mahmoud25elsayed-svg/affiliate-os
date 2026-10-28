<?php

declare(strict_types=1);

namespace App\Domain\Finance\Exceptions;

use RuntimeException;

class PayoutNotEligibleException extends RuntimeException
{
    public function __construct(string $message = 'المسوق غير مؤهل للسحب حالياً (حساب غير نشط، عدم توثيق الهوية، تجميد سحب، أو وجود رصيد مدين)')
    {
        parent::__construct($message);
    }
}
