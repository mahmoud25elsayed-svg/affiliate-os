<?php

declare(strict_types=1);

namespace App\Domain\Finance\Exceptions;

use RuntimeException;

class InvalidPayoutStatusTransitionException extends RuntimeException
{
    public function __construct(string $from, string $to)
    {
        parent::__construct("لا يمكن تحويل حالة طلب السحب من [{$from}] إلى [{$to}]");
    }
}
