<?php

declare(strict_types=1);

namespace App\Domain\Orders\Exceptions;

use App\Domain\Orders\Enums\OrderStatus;
use DomainException;

class ReasonCodeRequiredException extends DomainException
{
    public function __construct(
        public readonly OrderStatus $from,
        public readonly OrderStatus $to
    ) {
        parent::__construct(
            sprintf(
                'يتطلب الانتقال من حالة [%s] إلى [%s] تحديد كود سبب صالح (ReasonCode).',
                $from->label(),
                $to->label()
            )
        );
    }
}
