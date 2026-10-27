<?php

declare(strict_types=1);

namespace App\Domain\Notifications\DTOs;

class NotificationResult
{
    /**
     * @param  array<string, mixed>|null  $rawResponse
     */
    public function __construct(
        public readonly bool $success,
        public readonly ?string $externalId = null,
        public readonly int $costMinor = 0,
        public readonly ?string $errorMessage = null,
        public readonly ?array $rawResponse = null
    ) {}

    /**
     * @param  array<string, mixed>|null  $rawResponse
     */
    public static function success(?string $externalId = null, int $costMinor = 0, ?array $rawResponse = null): self
    {
        return new self(
            success: true,
            externalId: $externalId,
            costMinor: $costMinor,
            rawResponse: $rawResponse
        );
    }

    /**
     * @param  array<string, mixed>|null  $rawResponse
     */
    public static function failure(string $errorMessage, ?array $rawResponse = null): self
    {
        return new self(
            success: false,
            errorMessage: $errorMessage,
            rawResponse: $rawResponse
        );
    }
}
