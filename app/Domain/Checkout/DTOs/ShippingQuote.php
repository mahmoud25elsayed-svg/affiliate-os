<?php

declare(strict_types=1);

namespace App\Domain\Checkout\DTOs;

readonly class ShippingQuote
{
    public function __construct(
        public int $rate_minor,
        public string $delivery_time_ar,
        public bool $is_free,
        public ?string $free_reason = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'rate_minor' => $this->rate_minor,
            'rate_decimal' => $this->rate_minor / 100,
            'delivery_time_ar' => $this->delivery_time_ar,
            'is_free' => $this->is_free,
            'free_reason' => $this->free_reason,
        ];
    }
}
