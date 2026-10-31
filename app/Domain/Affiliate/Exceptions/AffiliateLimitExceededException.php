<?php

declare(strict_types=1);

namespace App\Domain\Affiliate\Exceptions;

use Exception;

class AffiliateLimitExceededException extends Exception
{
    public static function forLimit(int $limit): self
    {
        return new self(__('لقد استنفذ المتجر الحد الأقصى للمسوقين المتاح في باقة الاشتراك (:limit مسوق). يرجى ترقية الباقة لإضافة المزيد من المسوقين.', ['limit' => $limit]));
    }

    public static function featureDisabled(): self
    {
        return new self(__('برنامج المسوقين غير مفعل في خطة الاشتراك الحالية للمتجر.'));
    }
}
