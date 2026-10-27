<?php

declare(strict_types=1);

namespace App\Support\Billing\Exceptions;

use DomainException;

class LimitExceededException extends DomainException
{
    public function __construct(
        public readonly string $limitKey,
        public readonly int $maxLimit,
        public readonly int $currentUsage,
        string $message = ''
    ) {
        if ($message === '') {
            $label = match ($limitKey) {
                'products' => __('المنتجات'),
                'monthly_orders' => __('الطلبات الشهرية'),
                'affiliates' => __('المسوقين'),
                'staff' => __('الموظفين'),
                'storage_mb' => __('المساحة التخزينية'),
                default => $limitKey,
            };

            $message = __('لقد استنفدت الحد المسموح به لـ :label في خطتك الحالية (:max). يرجى الترقية لإضافة المزيد.', [
                'label' => $label,
                'max' => $maxLimit,
                'current' => $currentUsage,
            ]);
        }

        parent::__construct($message);
    }
}
