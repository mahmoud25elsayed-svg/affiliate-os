<?php

declare(strict_types=1);

namespace App\Domain\Finance\Enums;

enum CodRemittanceStatus: string
{
    case DRAFT = 'draft';
    case RECONCILED = 'reconciled';
    case DISCREPANT = 'discrepant';
    case SETTLED = 'settled';
    case CANCELLED = 'cancelled';

    public function label(string $locale = 'ar'): string
    {
        return match ($this) {
            self::DRAFT => $locale === 'ar' ? __('مسودة') : 'Draft',
            self::RECONCILED => $locale === 'ar' ? __('مطابق وجاهز للتسوية') : 'Reconciled',
            self::DISCREPANT => $locale === 'ar' ? __('يوجد فروقات') : 'Discrepant',
            self::SETTLED => $locale === 'ar' ? __('تمت التسوية والتوريد') : 'Settled',
            self::CANCELLED => $locale === 'ar' ? __('ملغي') : 'Cancelled',
        };
    }

    public function badgeColor(): string
    {
        return match ($this) {
            self::DRAFT => 'gray',
            self::RECONCILED => 'blue',
            self::DISCREPANT => 'amber',
            self::SETTLED => 'emerald',
            self::CANCELLED => 'red',
        };
    }

    public function isSettled(): bool
    {
        return $this === self::SETTLED;
    }
}
