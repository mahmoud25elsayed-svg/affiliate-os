<?php

declare(strict_types=1);

namespace App\Domain\Affiliate\Exceptions;

use Exception;

class AffiliateLimitExceededException extends Exception
{
    public static function forLimit(int $limit): self
    {
        return new self("لقد استنفذ المتجر الحد الأقصى للمسوقين المتاح في باقة الاشتراك ({$limit} مسوق). يرجى ترقية الباقة لإضافة المزيد من المسوقين.");
    }

    public static function featureDisabled(): self
    {
        return new self('برنامج المسوقين غير مفعل في خطة الاشتراك الحالية للمتجر.');
    }
}
