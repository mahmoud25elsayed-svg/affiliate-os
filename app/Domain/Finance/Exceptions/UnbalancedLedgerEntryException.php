<?php

declare(strict_types=1);

namespace App\Domain\Finance\Exceptions;

use DomainException;

class UnbalancedLedgerEntryException extends DomainException
{
    public function __construct(int $sumMinor)
    {
        parent::__construct(__('قيد دفتر غير متوازن: مجموع السطور المدفوعة والدائنة يجب أن يساوي صفراً (المجموع الحالي: :sum_minor).', ['sum_minor' => $sumMinor]));
    }
}
