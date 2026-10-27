<?php

declare(strict_types=1);

namespace App\Domain\Checkout\DTOs;

readonly class SpamCheckResult
{
    /**
     * @param  list<string>  $flags
     */
    public function __construct(
        public bool $isSuspicious,
        public bool $isDuplicate,
        public string $status, // 'pass', 'review', 'reject'
        public ?int $previousOrderId = null,
        public array $flags = [],
        public ?string $reasonAr = null,
    ) {}

    public static function pass(): self
    {
        return new self(
            isSuspicious: false,
            isDuplicate: false,
            status: 'pass',
            previousOrderId: null,
            flags: [],
            reasonAr: null
        );
    }

    /**
     * @param  list<string>  $flags
     */
    public static function duplicate(int $previousOrderId, array $flags = [], string $reasonAr = 'طلب مكرر لنفس رقم الهاتف والمنتج خلال دقائق معدودة'): self
    {
        return new self(
            isSuspicious: true,
            isDuplicate: true,
            status: 'review',
            previousOrderId: $previousOrderId,
            flags: $flags,
            reasonAr: $reasonAr
        );
    }
}
