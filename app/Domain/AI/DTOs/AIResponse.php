<?php

declare(strict_types=1);

namespace App\Domain\AI\DTOs;

class AIResponse
{
    /**
     * @param  array<string, mixed>  $rawPayload
     */
    public function __construct(
        public readonly string $content,
        public readonly int $promptTokens,
        public readonly int $completionTokens,
        public readonly int $totalTokens,
        public readonly string $model,
        public readonly string $provider,
        public readonly int $estimatedCostCents = 0,
        public readonly array $rawPayload = []
    ) {}
}
