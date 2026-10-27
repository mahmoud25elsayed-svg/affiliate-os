<?php

declare(strict_types=1);

namespace App\Support\Enums;

enum ReasonCode: string
{
    // Cancellation
    case CUSTOMER_CHANGED_MIND = 'customer_changed_mind';
    case DUPLICATE_ORDER = 'duplicate_order';
    case HIGH_PRICE = 'high_price';
    case LONG_DELIVERY_TIME = 'long_delivery_time';
    case OUT_OF_STOCK = 'out_of_stock';

    // Quality Rejection
    case INVALID_PHONE = 'invalid_phone';
    case INCOMPLETE_ADDRESS = 'incomplete_address';
    case SUSPECTED_FRAUD = 'suspected_fraud';
    case HIGH_RISK_REPUTATION = 'high_risk_reputation';

    // Delivery Failure
    case CUSTOMER_UNREACHABLE = 'customer_unreachable';
    case INCORRECT_ADDRESS = 'incorrect_address';
    case REFUSED_AT_DOORSTEP = 'refused_at_doorstep';
    case POSTPONED_EXCEEDED = 'postponed_exceeded';

    // Return
    case DEFECTIVE_PRODUCT = 'defective_product';
    case WRONG_ITEM_SENT = 'wrong_item_sent';
    case NOT_AS_DESCRIBED = 'not_as_described';
    case SIZE_MISMATCH = 'size_mismatch';

    public function type(): ReasonType
    {
        return match ($this) {
            self::CUSTOMER_CHANGED_MIND,
            self::DUPLICATE_ORDER,
            self::HIGH_PRICE,
            self::LONG_DELIVERY_TIME,
            self::OUT_OF_STOCK => ReasonType::CANCELLATION,

            self::INVALID_PHONE,
            self::INCOMPLETE_ADDRESS,
            self::SUSPECTED_FRAUD,
            self::HIGH_RISK_REPUTATION => ReasonType::QUALITY_REJECTION,

            self::CUSTOMER_UNREACHABLE,
            self::INCORRECT_ADDRESS,
            self::REFUSED_AT_DOORSTEP,
            self::POSTPONED_EXCEEDED => ReasonType::DELIVERY_FAILURE,

            self::DEFECTIVE_PRODUCT,
            self::WRONG_ITEM_SENT,
            self::NOT_AS_DESCRIBED,
            self::SIZE_MISMATCH => ReasonType::RETURN,
        };
    }

    public function label(string $locale = 'ar'): string
    {
        return match ($this) {
            self::CUSTOMER_CHANGED_MIND => $locale === 'ar' ? 'العميل غيّر رأيه' : 'Customer changed mind',
            self::DUPLICATE_ORDER => $locale === 'ar' ? 'طلب مكرر' : 'Duplicate order',
            self::HIGH_PRICE => $locale === 'ar' ? 'السعر مرتفع' : 'Price too high',
            self::LONG_DELIVERY_TIME => $locale === 'ar' ? 'مدة التوصيل طويلة' : 'Delivery time too long',
            self::OUT_OF_STOCK => $locale === 'ar' ? 'نفاد المخزون' : 'Out of stock',

            self::INVALID_PHONE => $locale === 'ar' ? 'رقم هاتف غير صحيح' : 'Invalid phone number',
            self::INCOMPLETE_ADDRESS => $locale === 'ar' ? 'عنوان غير مكتمل' : 'Incomplete address',
            self::SUSPECTED_FRAUD => $locale === 'ar' ? 'اشتباه احتيال' : 'Suspected fraud',
            self::HIGH_RISK_REPUTATION => $locale === 'ar' ? 'سجل رفض متكرر للعميل' : 'High risk customer reputation',

            self::CUSTOMER_UNREACHABLE => $locale === 'ar' ? 'العميل لا يجيب' : 'Customer unreachable',
            self::INCORRECT_ADDRESS => $locale === 'ar' ? 'العنوان خاطئ' : 'Incorrect address',
            self::REFUSED_AT_DOORSTEP => $locale === 'ar' ? 'رفض الاستلام عند الباب' : 'Refused at doorstep',
            self::POSTPONED_EXCEEDED => $locale === 'ar' ? 'تأجيل متكرر تجاوز الحد' : 'Repeatedly postponed',

            self::DEFECTIVE_PRODUCT => $locale === 'ar' ? 'منتج معيب أو تالف' : 'Defective or damaged product',
            self::WRONG_ITEM_SENT => $locale === 'ar' ? 'استلام منتج مختلف' : 'Wrong item sent',
            self::NOT_AS_DESCRIBED => $locale === 'ar' ? 'المنتج لا يطابق الوصف' : 'Not as described',
            self::SIZE_MISMATCH => $locale === 'ar' ? 'المقاس غير مناسب' : 'Size mismatch',
        };
    }
}
