<?php

declare(strict_types=1);

namespace App\Domain\Checkout\DTOs;

readonly class CheckoutPayload
{
    /**
     * @param  list<array{purchasable_id: int, quantity: int}>  $items
     */
    public function __construct(
        public string $customer_name,
        public string $customer_phone,
        public int $governorate_id,
        public string $city,
        public string $address,
        public ?string $notes = null,
        public string $payment_method = 'cod',
        public ?string $coupon_code = null,
        public array $items = [],
        public ?string $idempotency_token = null,
        public ?int $customer_id = null,
        public ?string $affiliate_ref_token = null,
    ) {}
}
