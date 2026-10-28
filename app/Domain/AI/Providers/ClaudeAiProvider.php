<?php

declare(strict_types=1);

namespace App\Domain\AI\Providers;

use App\Domain\AI\Contracts\AIProvider;
use App\Domain\AI\DTOs\AIResponse;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class ClaudeAiProvider implements AIProvider
{
    protected const DEFAULT_ENDPOINT = 'https://api.anthropic.com/v1/messages';

    protected const ANTHROPIC_VERSION = '2023-06-01';

    protected const DEFAULT_MODEL = 'claude-3-5-haiku-20241022';

    protected string $endpoint;

    public function __construct(
        protected ?string $apiKey = null,
        protected ?string $model = null,
        ?string $endpoint = null
    ) {
        $this->apiKey = $this->apiKey ?: (string) config('services.anthropic.key');
        $this->model = $this->model ?: (string) (config('services.anthropic.model') ?: self::DEFAULT_MODEL);
        $this->endpoint = $endpoint ?: self::DEFAULT_ENDPOINT;
    }

    public function getName(): string
    {
        return 'claude';
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public function generate(string $systemPrompt, string $userPrompt, array $options = []): AIResponse
    {
        if (empty($this->apiKey)) {
            throw new RuntimeException('Anthropic Claude API key is not configured.');
        }

        $model = (string) ($options['model'] ?? $this->model);
        $maxTokens = (int) ($options['max_tokens'] ?? 1024);
        $temperature = (float) ($options['temperature'] ?? 0.7);

        $payload = [
            'model' => $model,
            'max_tokens' => $maxTokens,
            'temperature' => $temperature,
            'system' => $systemPrompt,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $userPrompt,
                ],
            ],
        ];

        $response = Http::withHeaders([
            'x-api-key' => $this->apiKey,
            'anthropic-version' => self::ANTHROPIC_VERSION,
            'content-type' => 'application/json',
        ])->timeout(30)->post($this->endpoint, $payload);

        if ($response->failed()) {
            $errorMsg = $response->json('error.message') ?? $response->body();

            throw new RuntimeException("Claude API request failed ({$response->status()}): {$errorMsg}");
        }

        $data = $response->json();
        $content = '';
        if (isset($data['content']) && is_array($data['content'])) {
            foreach ($data['content'] as $block) {
                if (isset($block['type']) && $block['type'] === 'text' && isset($block['text'])) {
                    $content .= (string) $block['text'];
                }
            }
        }

        $inputTokens = (int) ($data['usage']['input_tokens'] ?? 0);
        $outputTokens = (int) ($data['usage']['output_tokens'] ?? 0);
        $totalTokens = $inputTokens + $outputTokens;

        // Cost estimation: Haiku (~$0.80/M input, ~$4.00/M output).
        // 10,000 input tokens = 0.8 cents. Integer minor cents:
        $costInCents = (int) ceil(($inputTokens * 0.00008) + ($outputTokens * 0.0004));

        return new AIResponse(
            content: trim($content),
            promptTokens: $inputTokens,
            completionTokens: $outputTokens,
            totalTokens: $totalTokens,
            model: $model,
            provider: 'claude',
            estimatedCostCents: $costInCents,
            rawPayload: is_array($data) ? $data : []
        );
    }
}
