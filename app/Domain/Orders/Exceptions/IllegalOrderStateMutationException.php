<?php

declare(strict_types=1);

namespace App\Domain\Orders\Exceptions;

use LogicException;

class IllegalOrderStateMutationException extends LogicException
{
    public function __construct(string $message = 'تعديل حالة الطلب مباشرة محظور تماماً. يجب استخدام OrderTransitionService.')
    {
        parent::__construct($message);
    }
}
