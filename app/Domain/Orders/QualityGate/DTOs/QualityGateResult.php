<?php

declare(strict_types=1);

namespace App\Domain\Orders\QualityGate\DTOs;

readonly class QualityGateResult
{
    public const STATUS_PASS = 'pass';

    public const STATUS_REVIEW = 'review';

    public const STATUS_REJECT = 'reject';

    /**
     * @param  list<string>  $flags
     * @param  list<string>  $reasons
     */
    public function __construct(
        public string $status,
        public array $flags = [],
        public array $reasons = [],
        public int $riskScore = 0
    ) {}

    public function isPass(): bool
    {
        return $this->status === self::STATUS_PASS;
    }

    public function isReview(): bool
    {
        return $this->status === self::STATUS_REVIEW;
    }

    public function isReject(): bool
    {
        return $this->status === self::STATUS_REJECT;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'status' => $this->status,
            'flags' => $this->flags,
            'reasons' => $this->reasons,
            'risk_score' => $this->riskScore,
        ];
    }
}
