<?php

declare(strict_types=1);

namespace App\Domain\Orders\Exceptions;

use App\Domain\Orders\DTOs\OrderActor;
use App\Domain\Orders\Enums\OrderStatus;
use DomainException;

class InvalidOrderTransitionException extends DomainException
{
    public function __construct(
        public readonly OrderStatus $from,
        public readonly OrderStatus $to,
        public readonly ?OrderActor $actor = null,
        string $message = ''
    ) {
        $msg = $message !== '' ? $message : sprintf(
            'لا يمكن الانتقال من حالة [%s] إلى حالة [%s]%s.',
            $from->label(),
            $to->label(),
            $actor !== null ? " بواسطة [{$actor->type}]" : ''
        );

        parent::__construct($msg);
    }
}
