<?php

declare(strict_types=1);

namespace App\Support\Billing\Exceptions;

use DomainException;

class FeatureNotAvailableException extends DomainException
{
    public function __construct(
        public readonly string $feature,
        string $message = ''
    ) {
        if ($message === '') {
            $message = __("الميزة ':feature' غير متاحة في باقتك الحالية. يرجى ترقية الخطة للاستفادة منها.", [
                'feature' => $feature,
            ]);
        }

        parent::__construct($message);
    }
}
