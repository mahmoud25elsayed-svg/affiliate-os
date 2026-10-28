<?php

declare(strict_types=1);

namespace App\Domain\AI\Services;

use App\Domain\AI\Contracts\AIProvider;
use App\Domain\AI\DTOs\AIResponse;
use App\Domain\AI\Exceptions\AiQuotaExceededException;
use App\Models\AiUsageLog;
use App\Models\Tenant;
use App\Support\Billing\Features;
use App\Support\Billing\Limits;
use Illuminate\Support\Facades\Auth;
use Throwable;

class AIManager
{
    public function __construct(
        protected AIProvider $provider,
        protected CustomerDataSanitizer $sanitizer
    ) {}

    public function getProvider(): AIProvider
    {
        return $this->provider;
    }

    public function setProvider(AIProvider $provider): self
    {
        $this->provider = $provider;

        return $this;
    }

    /**
     * Central execution pipeline for all AI tasks.
     * Enforces:
     * 1. Feature flag ('ai')
     * 2. Monthly quota limit
     * 3. Zero-PII privacy assertion
     * 4. Usage logging
     *
     * @param  array<string, mixed>  $options
     */
    public function execute(
        string $action,
        string $systemPrompt,
        string $userPrompt,
        ?Tenant $tenant = null,
        ?int $affiliateId = null,
        array $options = []
    ): AIResponse {
        $tenant = Features::resolveTenant($tenant);

        if ($tenant) {
            // 1. Ensure 'ai' feature is enabled on the tenant's plan
            Features::ensure('ai', $tenant);

            // 2. Enforce monthly requests limit
            $maxLimit = Limits::get('ai_requests_monthly', $tenant);
            if ($maxLimit > 0) {
                $currentUsage = Limits::usage('ai_requests_monthly', $tenant);
                if ($currentUsage >= $maxLimit) {
                    throw new AiQuotaExceededException($maxLimit, $currentUsage);
                }
            }
        }

        // 3. Strict Customer PII assertion (Zero PII sent to AI models)
        $this->sanitizer->assertNoCustomerPii($systemPrompt, $userPrompt);

        $rawUserId = Auth::id();
        $userId = is_numeric($rawUserId) ? (int) $rawUserId : null;
        $status = AiUsageLog::STATUS_SUCCESS;
        $errorMessage = null;

        try {
            $response = $this->provider->generate($systemPrompt, $userPrompt, $options);
        } catch (Throwable $e) {
            $status = AiUsageLog::STATUS_FAILED;
            $errorMessage = $e->getMessage();

            if ($tenant) {
                $this->logUsage(
                    tenantId: (string) $tenant->id,
                    userId: $userId,
                    affiliateId: $affiliateId,
                    action: $action,
                    provider: $this->provider->getName(),
                    model: 'unknown',
                    promptTokens: 0,
                    completionTokens: 0,
                    totalTokens: 0,
                    estimatedCostCents: 0,
                    status: $status,
                    errorMessage: $errorMessage
                );
            }

            throw $e;
        }

        if ($tenant) {
            $this->logUsage(
                tenantId: (string) $tenant->id,
                userId: $userId,
                affiliateId: $affiliateId,
                action: $action,
                provider: $response->provider,
                model: $response->model,
                promptTokens: $response->promptTokens,
                completionTokens: $response->completionTokens,
                totalTokens: $response->totalTokens,
                estimatedCostCents: $response->estimatedCostCents,
                status: $status,
                errorMessage: null
            );
        }

        return $response;
    }

    protected function logUsage(
        string $tenantId,
        ?int $userId,
        ?int $affiliateId,
        string $action,
        string $provider,
        string $model,
        int $promptTokens,
        int $completionTokens,
        int $totalTokens,
        int $estimatedCostCents,
        string $status,
        ?string $errorMessage
    ): void {
        AiUsageLog::create([
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'affiliate_id' => $affiliateId,
            'action' => $action,
            'provider' => $provider,
            'model' => $model,
            'prompt_tokens' => $promptTokens,
            'completion_tokens' => $completionTokens,
            'total_tokens' => $totalTokens,
            'estimated_cost_cents' => $estimatedCostCents,
            'status' => $status,
            'error_message' => $errorMessage,
        ]);
    }
}
