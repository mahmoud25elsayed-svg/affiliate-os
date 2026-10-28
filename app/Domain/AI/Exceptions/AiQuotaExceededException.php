<?php

declare(strict_types=1);

namespace App\Domain\AI\Exceptions;

use RuntimeException;

class AiQuotaExceededException extends RuntimeException
{
    public function __construct(int $limit, int $current)
    {
        parent::__construct("لقد استنفذت الحصة الشهرية لطلبات الذكاء الاصطناعي ({$current}/{$limit} طلب). يرجى ترقية باقة اشتراكك للاستمرار.");
    }
}
