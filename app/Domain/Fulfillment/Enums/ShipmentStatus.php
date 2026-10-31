<?php

declare(strict_types=1);

namespace App\Domain\Fulfillment\Enums;

enum ShipmentStatus: string
{
    case DRAFT = 'draft';
    case CREATED = 'created';
    case PICKED_UP = 'picked_up';
    case IN_TRANSIT = 'in_transit';
    case OUT_FOR_DELIVERY = 'out_for_delivery';
    case DELIVERED = 'delivered';
    case FAILED_ATTEMPT = 'failed_attempt';
    case RETURNING = 'returning';
    case RETURNED = 'returned';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => __('مسودة'),
            self::CREATED => __('تم تسجيل الشحنة وإصدار البوليصة'),
            self::PICKED_UP => __('تم الاستلام من المخزن'),
            self::IN_TRANSIT => __('في الطريق لمركز التوزيع'),
            self::OUT_FOR_DELIVERY => __('خرج للتسليم مع المندوب'),
            self::DELIVERED => __('تم التسليم والتحصيل بنجاح'),
            self::FAILED_ATTEMPT => __('تعذر التسليم (محاولة)'),
            self::RETURNING => __('قيد الإرجاع للمتجر'),
            self::RETURNED => __('مرتجع مستلم بالمخزن'),
            self::CANCELLED => __('شحنة ملغاة'),
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::DELIVERED, self::RETURNED, self::CANCELLED], true);
    }

    public function isDelivered(): bool
    {
        return $this === self::DELIVERED;
    }

    public function isReturning(): bool
    {
        return in_array($this, [self::RETURNING, self::RETURNED], true);
    }

    public function color(): string
    {
        return match ($this) {
            self::DRAFT => 'slate',
            self::CREATED => 'indigo',
            self::PICKED_UP, self::IN_TRANSIT => 'blue',
            self::OUT_FOR_DELIVERY => 'amber',
            self::DELIVERED => 'emerald',
            self::FAILED_ATTEMPT, self::RETURNING, self::RETURNED, self::CANCELLED => 'rose',
        };
    }
}
