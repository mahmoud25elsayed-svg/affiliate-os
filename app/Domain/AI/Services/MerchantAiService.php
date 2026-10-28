<?php

declare(strict_types=1);

namespace App\Domain\AI\Services;

use App\Models\AiUsageLog;
use App\Models\Tenant;

class MerchantAiService
{
    public function __construct(
        protected AIManager $aiManager
    ) {}

    /**
     * Generate product marketing copy, bullet points, and SEO metadata.
     *
     * @return array{headline: string, description: string, bullet_points: list<string>, seo_title: string, seo_description: string}
     */
    public function generateProductCopy(
        string $title,
        ?string $features = null,
        string $dialect = 'egyptian',
        string $tone = 'persuasive',
        ?Tenant $tenant = null
    ): array {
        $dialectInstruction = match ($dialect) {
            'msa' => 'اكتب بالعربية الفصحى التسويقية الأنيقة والسلسة.',
            'english' => 'Write in high-converting, professional e-commerce English.',
            default => 'اكتب باللهجة العامية المصرية الجذابة والقريبة لقلب المتسوق المصري في التجارة الإلكترونية.',
        };

        $systemPrompt = "أنت خبير محتوى تسويقي وخبير تحسين محركات البحث (SEO) للمتاجر الإلكترونية في مصر والشرق الأوسط.\n"
            ."{$dialectInstruction}\n"
            ."يجب أن تُعيد الإجابة بصيغة JSON فقط دون أي نصوص إضافية، بالشكل التالي:\n"
            ."{\n"
            ."  \"headline\": \"عنوان تسويقي جذاب ومثير للاهتمام\",\n"
            ."  \"description\": \"وصف تسويقي للمنتج يركز على الفوائد لا الخصائص فقط\",\n"
            ."  \"bullet_points\": [\"ميزة 1\", \"ميزة 2\", \"ميزة 3\", \"ميزة 4\"],\n"
            ."  \"seo_title\": \"عنوان SEO لا يتجاوز 60 حرفاً ومحفز للنقر\",\n"
            ."  \"seo_description\": \"وصف ميتا لا يتجاوز 155 حرفاً يحتوي كلمات مفتاحية ودعوة للشراء\"\n"
            .'}';

        $userPrompt = "اسم المنتج: {$title}\n"
            .($features ? "مميزات ومواصفات المنتج: {$features}\n" : '')
            ."النبرة المطلوبة: {$tone}\n"
            .'اكتب وصفاً مبهراً يزيد المبيعات ومحسناً لمحركات البحث.';

        $response = $this->aiManager->execute(
            action: AiUsageLog::ACTION_PRODUCT_COPY,
            systemPrompt: $systemPrompt,
            userPrompt: $userPrompt,
            tenant: $tenant
        );

        return $this->parseJson($response->content, [
            'headline' => $title,
            'description' => $title,
            'bullet_points' => [],
            'seo_title' => $title,
            'seo_description' => $title,
        ]);
    }

    /**
     * Suggest categories and search tags based on product title and details.
     *
     * @return array{categories: list<string>, tags: list<string>}
     */
    public function suggestCategoriesAndTags(
        string $title,
        ?string $description = null,
        ?Tenant $tenant = null
    ): array {
        $systemPrompt = "أنت مساعد متخصص في تصنيف منتجات المتاجر الإلكترونية وإدارة الكتالوج.\n"
            ."اقترح أفضل تصنيفات (categories) ووسوم بحثية (tags) شائعة بين المشترين العرب.\n"
            ."أجب بصيغة JSON فقط:\n"
            ."{\n"
            ."  \"categories\": [\"تصنيف رئيسي\", \"تصنيف فرعي 1\", \"تصنيف فرعي 2\"],\n"
            ."  \"tags\": [\"وسم 1\", \"وسم 2\", \"وسم 3\", \"وسم 4\", \"وسم 5\"]\n"
            .'}';

        $userPrompt = "اسم المنتج: {$title}\n"
            .($description ? "وصف المنتج: {$description}\n" : '')
            .'اقترح تصنيفات ووسوم مناسبة.';

        $response = $this->aiManager->execute(
            action: AiUsageLog::ACTION_CATEGORY_TAGS,
            systemPrompt: $systemPrompt,
            userPrompt: $userPrompt,
            tenant: $tenant
        );

        return $this->parseJson($response->content, [
            'categories' => [],
            'tags' => [],
        ]);
    }

    /**
     * Translate product marketing content between Arabic and English.
     */
    public function translateContent(
        string $text,
        string $targetLanguage = 'en',
        ?Tenant $tenant = null
    ): string {
        $targetDesc = $targetLanguage === 'ar' ? 'العربية' : 'الإنجليزية';

        $systemPrompt = "أنت مترجم محترف متخصص في صياغة المحتوى التسويقي والإعلاني للمنتجات من وإلى اللغة {$targetDesc}.\n"
            ."ترجم النص مع الحفاظ على النبرة التسويقية الجذابة والأسلوب التجاري السلس، بدون ترجمة حرفية جافة.\n"
            .'أعد النص المترجم مباشرة بدون أي مقدمات.';

        $userPrompt = "النص المطلوب ترجمته:\n{$text}";

        $response = $this->aiManager->execute(
            action: AiUsageLog::ACTION_TRANSLATE,
            systemPrompt: $systemPrompt,
            userPrompt: $userPrompt,
            tenant: $tenant
        );

        return $response->content;
    }

    /**
     * @template T of array
     *
     * @param  T  $default
     * @return T
     */
    protected function parseJson(string $jsonString, array $default): array
    {
        // Clean markdown backticks if returned
        $cleaned = preg_replace('/^```(?:json)?\s*/i', '', trim($jsonString));
        $cleaned = preg_replace('/\s*```$/', '', (string) $cleaned);

        $decoded = json_decode((string) $cleaned, true);

        if (is_array($decoded)) {
            /** @var T */
            return array_merge($default, $decoded);
        }

        return $default;
    }
}
