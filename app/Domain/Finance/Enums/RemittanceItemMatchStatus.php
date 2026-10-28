<?php

declare(strict_types=1);

namespace App\Domain\Finance\Enums;

enum RemittanceItemMatchStatus: string
{
    case MATCHED = 'matched';
    case AMOUNT_MISMATCH = 'amount_mismatch';
    case FEE_MISMATCH = 'fee_mismatch';
    case NOT_FOUND = 'not_found';
    case ALREADY_SETTLED = 'already_settled';

    public function label(string $locale = 'ar'): string
    {
        return match ($this) {
            self::MATCHED => $locale === 'ar' ? 'مطابق تماماً' : 'Matched',
            self::AMOUNT_MISMATCH => $locale === 'ar' ? 'اختلاف مبلغ التحصيل' : 'Amount Mismatch',
            self::FEE_MISMATCH => $locale === 'ar' ? 'اختلاف مصاريف الشحن' : 'Fee Mismatch',
            self::NOT_FOUND => $locale === 'ar' ? 'شحنة غير معروفة' : 'Not Found',
            self::ALREADY_SETTLED => $locale === 'ar' ? 'تمت تسويتها مسبقاً' : 'Already Settled',
        };
    }

    public function isDiscrepancy(): bool
    {
        return $this !== self::MATCHED;
    }
}

