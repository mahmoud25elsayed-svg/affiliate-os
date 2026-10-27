<?php

declare(strict_types=1);

namespace App\Domain\Checkout\DTOs;

use App\Models\Coupon;

readonly class CouponResult
{
    public function __construct(
        public bool $isValid,
        public ?Coupon $coupon = null,
        public int $discountMinor = 0,
        public bool $isFreeShipping = false,
        public ?string $errorMessage = null,
    ) {}

    public static function success(Coupon $coupon, int $discountMinor, bool $isFreeShipping = false): self
    {
        return new self(
            isValid: true,
            coupon: $coupon,
            discountMinor: $discountMinor,
            isFreeShipping: $isFreeShipping,
            errorMessage: null
        );
    }

    public static function failure(string $message): self
    {
        return new self(
            isValid: false,
            coupon: null,
            discountMinor: 0,
            isFreeShipping: false,
            errorMessage: $message
        );
    }
}
