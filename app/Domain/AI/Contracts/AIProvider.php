<?php

declare(strict_types=1);

namespace App\Domain\AI\Contracts;

use App\Domain\AI\DTOs\AIResponse;

interface AIProvider
{
    /**
     * @param  array<string, mixed>  $options
     */
    public function generate(string $systemPrompt, string $userPrompt, array $options = []): AIResponse;

    public function getName(): string;
}
