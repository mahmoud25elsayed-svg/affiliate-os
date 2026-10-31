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
            self::MATCHED => $locale === 'ar' ? __('مطابق تماماً') : 'Matched',
            self::AMOUNT_MISMATCH => $locale === 'ar' ? __('اختلاف مبلغ التحصيل') : 'Amount Mismatch',
            self::FEE_MISMATCH => $locale === 'ar' ? __('اختلاف مصاريف الشحن') : 'Fee Mismatch',
            self::NOT_FOUND => $locale === 'ar' ? __('شحنة غير معروفة') : 'Not Found',
            self::ALREADY_SETTLED => $locale === 'ar' ? __('تمت تسويتها مسبقاً') : 'Already Settled',
        };
    }

    public function isDiscrepancy(): bool
    {
        return $this !== self::MATCHED;
    }
}
