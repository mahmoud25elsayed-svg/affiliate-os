<?php

declare(strict_types=1);

namespace App\Domain\Finance\Exceptions;

use RuntimeException;

class InsufficientAffiliateBalanceException extends RuntimeException
{
    public function __construct(string $message = 'الرصيد المتاح غير كافٍ لطلب هذا المبلغ للسحب')
    {
        parent::__construct($message);
    }
}
