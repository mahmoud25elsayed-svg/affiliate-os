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
            self::DRAFT => $locale === 'ar' ? 'مسودة' : 'Draft',
            self::RECONCILED => $locale === 'ar' ? 'مطابق وجاهز للتسوية' : 'Reconciled',
            self::DISCREPANT => $locale === 'ar' ? 'يوجد فروقات' : 'Discrepant',
            self::SETTLED => $locale === 'ar' ? 'تمت التسوية والتوريد' : 'Settled',
            self::CANCELLED => $locale === 'ar' ? 'ملغي' : 'Cancelled',
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
