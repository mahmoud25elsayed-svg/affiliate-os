<?php

declare(strict_types=1);

namespace App\Domain\Orders\Enums;

enum OrderStatus: string
{
    case SUBMITTED = 'submitted';
    case REJECTED_QUALITY = 'rejected_quality';
    case PENDING_CONFIRMATION = 'pending_confirmation';
    case CONFIRMED = 'confirmed';
    case UNREACHABLE = 'unreachable';
    case CANCELLED_BY_CUSTOMER = 'cancelled_by_customer';
    case CANCELLED_NO_ANSWER = 'cancelled_no_answer';
    case RESERVED = 'reserved';
    case OUT_OF_STOCK = 'out_of_stock';
    case PACKED = 'packed';
    case SHIPPED = 'shipped';
    case OUT_FOR_DELIVERY = 'out_for_delivery';
    case DELIVERED = 'delivered';
    case DELIVERY_FAILED = 'delivery_failed';
    case RETURNING = 'returning';
    case RETURN_REQUESTED = 'return_requested';
    case RETURNED = 'returned';
    case SETTLED = 'settled';

    public function label(string $locale = 'ar'): string
    {
        return match ($this) {
            self::SUBMITTED => $locale === 'ar' ? __('تم الإرسال') : 'Submitted',
            self::REJECTED_QUALITY => $locale === 'ar' ? __('مرفوض فحص الجودة') : 'Quality Rejected',
            self::PENDING_CONFIRMATION => $locale === 'ar' ? __('بانتظار التأكيد') : 'Pending Confirmation',
            self::CONFIRMED => $locale === 'ar' ? __('تم التأكيد') : 'Confirmed',
            self::UNREACHABLE => $locale === 'ar' ? __('تعذر الاتصال') : 'Unreachable',
            self::CANCELLED_BY_CUSTOMER => $locale === 'ar' ? __('ملغي بواسطة العميل') : 'Cancelled by Customer',
            self::CANCELLED_NO_ANSWER => $locale === 'ar' ? __('ملغي لعدم الرد') : 'Cancelled (No Answer)',
            self::RESERVED => $locale === 'ar' ? __('محجوز بالمخزن') : 'Stock Reserved',
            self::OUT_OF_STOCK => $locale === 'ar' ? __('نفاد المخزون') : 'Out of Stock',
            self::PACKED => $locale === 'ar' ? __('تم التجهيز والتعبئة') : 'Packed',
            self::SHIPPED => $locale === 'ar' ? __('تم التسليم لشركة الشحن') : 'Shipped',
            self::OUT_FOR_DELIVERY => $locale === 'ar' ? __('خرج للتوصيل') : 'Out for Delivery',
            self::DELIVERED => $locale === 'ar' ? __('تم التسليم') : 'Delivered',
            self::DELIVERY_FAILED => $locale === 'ar' ? __('تعذر التسليم') : 'Delivery Failed',
            self::RETURNING => $locale === 'ar' ? __('قيد الإرجاع') : 'Returning',
            self::RETURN_REQUESTED => $locale === 'ar' ? __('طلب مرتجع') : 'Return Requested',
            self::RETURNED => $locale === 'ar' ? __('تم الإرجاع للمخزن') : 'Returned',
            self::SETTLED => $locale === 'ar' ? __('تمت التسوية المالية') : 'Settled',
        };
    }

    public function customerLabel(string $locale = 'ar'): string
    {
        return match ($this) {
            self::SUBMITTED => $locale === 'ar' ? __('تم استلام طلبك بنجاح') : 'Order received',
            self::REJECTED_QUALITY => $locale === 'ar' ? __('الطلب غير مكتمل') : 'Incomplete details',
            self::PENDING_CONFIRMATION => $locale === 'ar' ? __('قيد مراجعة وتأكيد البيانات') : 'Awaiting confirmation',
            self::CONFIRMED => $locale === 'ar' ? __('تم تأكيد طلبك') : 'Order confirmed',
            self::UNREACHABLE => $locale === 'ar' ? __('تعذر الاتصال بك') : 'Could not reach you',
            self::CANCELLED_BY_CUSTOMER => $locale === 'ar' ? __('تم إلغاء الطلب بناءً على رغبتك') : 'Cancelled by request',
            self::CANCELLED_NO_ANSWER => $locale === 'ar' ? __('تم إلغاء الطلب لتعذر التواصل') : 'Cancelled (unreachable)',
            self::RESERVED => $locale === 'ar' ? __('جاري تجهيز المنتجات في المستودع') : 'Preparing at warehouse',
            self::OUT_OF_STOCK => $locale === 'ar' ? __('نعتذر، نفد مخزون بعض الأصناف') : 'Item out of stock',
            self::PACKED => $locale === 'ar' ? __('طلبك جاهز وفي انتظار التسليم للشحن') : 'Packed and ready',
            self::SHIPPED => $locale === 'ar' ? __('تم تسليم الشحنة لشركة الشحن') : 'Handed to courier',
            self::OUT_FOR_DELIVERY => $locale === 'ar' ? __('مندوب الشحن في الطريق إليك') : 'Out for delivery',
            self::DELIVERED => $locale === 'ar' ? __('تم تسليم طلبك بنجاح. شكراً لثقتك بنا!') : 'Delivered successfully',
            self::DELIVERY_FAILED => $locale === 'ar' ? __('تعذر تسليم الشحنة') : 'Delivery unsuccessful',
            self::RETURNING => $locale === 'ar' ? __('الشحنة في طريق العودة') : 'Returning to sender',
            self::RETURN_REQUESTED => $locale === 'ar' ? __('طلب الإرجاع قيد المراجعة') : 'Return under review',
            self::RETURNED => $locale === 'ar' ? __('تم استلام المرتجع في المستودع') : 'Return received',
            self::SETTLED => $locale === 'ar' ? __('مكتمل') : 'Completed',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::SUBMITTED => 'gray',
            self::REJECTED_QUALITY => 'danger',
            self::PENDING_CONFIRMATION => 'warning',
            self::CONFIRMED => 'info',
            self::UNREACHABLE => 'warning',
            self::CANCELLED_BY_CUSTOMER => 'danger',
            self::CANCELLED_NO_ANSWER => 'danger',
            self::RESERVED => 'info',
            self::OUT_OF_STOCK => 'danger',
            self::PACKED => 'info',
            self::SHIPPED => 'primary',
            self::OUT_FOR_DELIVERY => 'primary',
            self::DELIVERED => 'success',
            self::DELIVERY_FAILED => 'danger',
            self::RETURNING => 'warning',
            self::RETURN_REQUESTED => 'warning',
            self::RETURNED => 'gray',
            self::SETTLED => 'success',
        };
    }

    public function isTerminal(): bool
    {
        return match ($this) {
            self::REJECTED_QUALITY,
            self::CANCELLED_BY_CUSTOMER,
            self::CANCELLED_NO_ANSWER,
            self::OUT_OF_STOCK,
            self::RETURNED,
            self::SETTLED => true,
            default => false,
        };
    }

    public function isNegative(): bool
    {
        return match ($this) {
            self::REJECTED_QUALITY,
            self::CANCELLED_BY_CUSTOMER,
            self::CANCELLED_NO_ANSWER,
            self::OUT_OF_STOCK,
            self::DELIVERY_FAILED,
            self::RETURNING,
            self::RETURNED => true,
            default => false,
        };
    }
}
