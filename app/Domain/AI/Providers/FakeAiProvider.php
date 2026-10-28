<?php

declare(strict_types=1);

namespace App\Domain\AI\Providers;

use App\Domain\AI\Contracts\AIProvider;
use App\Domain\AI\DTOs\AIResponse;

class FakeAiProvider implements AIProvider
{
    /**
     * @var list<array{system: string, user: string, options: array<string, mixed>}>
     */
    public array $recordedCalls = [];

    /**
     * @var array<string, string>
     */
    protected array $cannedResponses = [];

    protected ?string $defaultResponse = null;

    public function getName(): string
    {
        return 'fake';
    }

    public function setDefaultResponse(string $response): self
    {
        $this->defaultResponse = $response;

        return $this;
    }

    public function setCannedResponse(string $key, string $response): self
    {
        $this->cannedResponses[$key] = $response;

        return $this;
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public function generate(string $systemPrompt, string $userPrompt, array $options = []): AIResponse
    {
        $this->recordedCalls[] = [
            'system' => $systemPrompt,
            'user' => $userPrompt,
            'options' => $options,
        ];

        $content = $this->resolveResponse($systemPrompt, $userPrompt);

        return new AIResponse(
            content: $content,
            promptTokens: 120,
            completionTokens: 250,
            totalTokens: 370,
            model: 'fake-model',
            provider: 'fake',
            estimatedCostCents: 1,
            rawPayload: ['mock' => true]
        );
    }

    protected function resolveResponse(string $systemPrompt, string $userPrompt): string
    {
        if ($this->defaultResponse !== null) {
            return $this->defaultResponse;
        }

        // Return canned response based on system or user prompt keywords
        foreach ($this->cannedResponses as $key => $res) {
            if (str_contains($systemPrompt, $key) || str_contains($userPrompt, $key)) {
                return $res;
            }
        }

        // Default mock JSON based on action context
        if (str_contains($systemPrompt, 'product_copy') || str_contains($userPrompt, 'وصف المنتج')) {
            return json_encode([
                'headline' => 'سماعة بلوتوث لاسلكية عازلة للضوضاء بأداء أسطوري',
                'description' => 'استمتع بنقاء صوت استثنائي مع أحدث تقنيات عزل الضوضاء النشط والبطارية التي تدوم حتى 36 ساعة متواصلة. مصممة لتلائم نمط حياتك اليومي بأعلى معايير الراحة.',
                'bullet_points' => [
                    'عزل ضوضاء فعال بنسبة 95%',
                    'شحن سريع: 10 دقائق شحن تكفيك 4 ساعات استماع',
                    'مقاومة للماء والتعرق بمعيار IPX5',
                    'مايكروفون نقي للمكالمات بتقنية تقليل صدى الصوت',
                ],
                'seo_title' => 'شراء سماعة لاسلكية عازلة للضوضاء | أفضل سعر في مصر',
                'seo_description' => 'اطلب الآن أفضل سماعة رأس لاسلكية بتقنية عزل الضوضاء مع ضمان سنة كاملة وتوصيل سريع ودفع عند الاستلام.',
            ], JSON_UNESCAPED_UNICODE) ?: '{}';
        }

        if (str_contains($systemPrompt, 'category_tags')) {
            return json_encode([
                'categories' => ['إلكترونيات', 'صوتيات', 'ملحقات الهواتف'],
                'tags' => ['سماعة', 'بلوتوث', 'عزل الضوضاء', 'أيربودز', 'عروض مصر'],
            ], JSON_UNESCAPED_UNICODE) ?: '{}';
        }

        if (str_contains($systemPrompt, 'translate')) {
            return 'Enjoy pure high-fidelity sound with superior active noise cancellation and an extended 36-hour battery life. Designed for daily active lifestyle.';
        }

        if (str_contains($systemPrompt, 'ad_copy') || str_contains($systemPrompt, 'variants') || str_contains($systemPrompt, 'Media Buyer')) {
            return json_encode([
                'variants' => [
                    [
                        'primary_text' => 'زهقت من دوشة المواصلات ومكالمات الشغل اللي مش واضحة؟ الحل مع السماعة دي اللي بتعزلك تماماً عن أي دوشة حواليك.',
                        'headline' => 'خصم 30% وشحن مجاني لفترة محدودة!',
                        'cta' => 'اطلب الآن وادفع عند الاستلام',
                    ],
                    [
                        'primary_text' => 'بطارية بتفضل معاك 3 أيام كاملة بدون شحن! جودة صوت خرافية باس عميق ومايك نقي جداً للمكالمات.',
                        'headline' => 'العرض الأكثر مبيعاً في مصر',
                        'cta' => 'اشتري قطعتين واحصل على التوصيل مجاناً',
                    ],
                ],
            ], JSON_UNESCAPED_UNICODE) ?: '{}';
        }

        if (str_contains($systemPrompt, 'marketing_angles') || str_contains($systemPrompt, 'angles') || str_contains($systemPrompt, 'زوايا تسويقية')) {
            return json_encode([
                'angles' => [
                    'زاوية الراحة والتركيز في الشغل والدراسة',
                    'زاوية الرياضة والچيم وثبات السماعة',
                    'زاوية السفر والمواصلات وعزل الدوشة',
                    'زاوية الهدية القيمة بسعر ممتاز',
                ],
            ], JSON_UNESCAPED_UNICODE) ?: '{}';
        }

        if (str_contains($systemPrompt, 'policy_check') || str_contains($systemPrompt, 'risk_score') || str_contains($systemPrompt, 'مدقق سياسات')) {
            return json_encode([
                'is_compliant' => true,
                'risk_score' => 10,
                'flagged_phrases' => [],
                'suggestions' => ['النص متوافق تماماً مع سياسات إعلانات ميتا وتيك توك بدون وعود مبالغ فيها.'],
            ], JSON_UNESCAPED_UNICODE) ?: '{}';
        }

        return 'رد افتراضي للذكاء الاصطناعي';
    }
}
