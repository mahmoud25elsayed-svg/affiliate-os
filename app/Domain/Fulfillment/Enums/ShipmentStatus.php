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
            self::DRAFT => 'مسودة',
            self::CREATED => 'تم تسجيل الشحنة وإصدار البوليصة',
            self::PICKED_UP => 'تم الاستلام من المخزن',
            self::IN_TRANSIT => 'في الطريق لمركز التوزيع',
            self::OUT_FOR_DELIVERY => 'خرج للتسليم مع المندوب',
            self::DELIVERED => 'تم التسليم والتحصيل بنجاح',
            self::FAILED_ATTEMPT => 'تعذر التسليم (محاولة)',
            self::RETURNING => 'قيد الإرجاع للمتجر',
            self::RETURNED => 'مرتجع مستلم بالمخزن',
            self::CANCELLED => 'شحنة ملغاة',
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
