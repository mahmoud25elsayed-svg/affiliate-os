<?php

declare(strict_types=1);

namespace App\Domain\AI\Services;

use App\Models\AiUsageLog;
use App\Models\Tenant;

class AffiliateAiService
{
    public function __construct(
        protected AIManager $aiManager
    ) {}

    /**
     * Generate Ad Copy for Facebook or TikTok Ads.
     *
     * @return array{variants: list<array{primary_text: string, headline: string, cta: string}>}
     */
    public function generateAdCopy(
        string $productName,
        string $price,
        string $platform = 'facebook',
        string $tone = 'enthusiastic',
        ?string $targetAudience = null,
        ?int $affiliateId = null,
        ?Tenant $tenant = null
    ): array {
        $platformGuide = match ($platform) {
            'tiktok' => 'إعلانات تيك توك: أسلوب عفوي، يبدأ بهوك خاطف في أول 3 ثواني، ولغة عامية شبابية سريعة.',
            default => 'إعلانات فيسبوك وإنستجرام: نصوص واضحة ومقنعة تركز على نقاط الألم وفوائد المنتج مع دعوة واضحة للشراء والدفع عند الاستلام.',
        };

        $toneGuide = match ($tone) {
            'problem_solving' => 'نبرة حل المشكلة: ابدأ بالمشكلة التي يعاني منها العميل ثم قدم المنتج كحل مثالي وسريع.',
            'urgency' => 'نبرة الاستعجال: ركز على العرض المحدود والكمية التي قاربت على النفاد والشحن المجاني.',
            'storytelling' => 'نبرة القصة والتجربة الشخصية: شارك تجربة واقعية ومقنعة تبني الثقة.',
            default => 'نبرة حماسية: تشجع على الشراء وتبث الإيجابية في التجربة.',
        };

        $systemPrompt = "أنت خبير محترف في إعلانات السوشيال ميديا والتسويق بالعمولة (Media Buyer & Copywriter) في السوق المصري والعربي.\n"
            ."{$platformGuide}\n"
            ."{$toneGuide}\n"
            ."يجب أن تُعيد الإجابة بصيغة JSON فقط كالتالي:\n"
            ."{\n"
            ."  \"variants\": [\n"
            ."    {\n"
            ."      \"primary_text\": \"نص الإعلان الرئيسي مع هوك قوي وإيموجيز مناسبة\",\n"
            ."      \"headline\": \"عنوان الإعلان القصير الجذاب\",\n"
            ."      \"cta\": \"الدعوة لاتخاذ إجراء مثل: اطلب الآن والدفع عند الاستلام\"\n"
            ."    }\n"
            ."  ]\n"
            .'}';

        $userPrompt = "المنتج: {$productName}\n"
            ."السعر: {$price}\n"
            .($targetAudience ? "الجمهور المستهدف: {$targetAudience}\n" : '')
            .'اكتب نموذجين لإعلانات تسويقية احترافية ومحققة لأعلى معدلات تحويل.';

        $response = $this->aiManager->execute(
            action: AiUsageLog::ACTION_AD_COPY,
            systemPrompt: $systemPrompt,
            userPrompt: $userPrompt,
            tenant: $tenant,
            affiliateId: $affiliateId
        );

        return $this->parseJson($response->content, [
            'variants' => [],
        ]);
    }

    /**
     * Suggest creative marketing angles and hooks for an affiliate campaign.
     *
     * @return array{angles: list<string>}
     */
    public function suggestMarketingAngles(
        string $productName,
        ?string $targetAudience = null,
        ?int $affiliateId = null,
        ?Tenant $tenant = null
    ): array {
        $systemPrompt = "أنت مستشار استراتيجيات تسويق بالمحتوى والعمولة.\n"
            ."اقترح 4-5 زوايا تسويقية غير تقليدية ومبهرة لجذب اهتمام المشترين وحثهم على الطلب.\n"
            ."أجب بصيغة JSON فقط:\n"
            ."{\n"
            ."  \"angles\": [\"زاوية 1\", \"زاوية 2\", \"زاوية 3\", \"زاوية 4\"]\n"
            .'}';

        $userPrompt = "اسم المنتج: {$productName}\n"
            .($targetAudience ? "الجمهور المستهدف: {$targetAudience}\n" : '')
            .'اقترح زوايا تسويقية إبداعية تناسب السوق المصري والعربي.';

        $response = $this->aiManager->execute(
            action: AiUsageLog::ACTION_MARKETING_ANGLES,
            systemPrompt: $systemPrompt,
            userPrompt: $userPrompt,
            tenant: $tenant,
            affiliateId: $affiliateId
        );

        return $this->parseJson($response->content, [
            'angles' => [],
        ]);
    }

    /**
     * Check ad copy against ad policies (Meta/TikTok advertising standards) for exaggerated claims.
     *
     * @return array{is_compliant: bool, risk_score: int, flagged_phrases: list<string>, suggestions: list<string>}
     */
    public function checkPolicyCompliance(
        string $adText,
        ?int $affiliateId = null,
        ?Tenant $tenant = null
    ): array {
        $systemPrompt = "أنت مدقق سياسات إعلانية خبير في قواعد قبول الإعلانات على فيسبوك وميتا وتيك توك.\n"
            ."افحص نص الإعلان وتأكد من خلوه من:\n"
            ."1. الوعود العلاجية أو الطبية الخارقة (مثل: علاج نهائي، شفاء 100%).\n"
            ."2. الوعود المالية غير الواقعية أو الثراء السريع.\n"
            ."3. التضليل وادعاءات قبل وبعد المزيفة أو الإلحاح المبالغ فيه.\n"
            ."قيم نسبة الخطر (risk_score) من 0 إلى 100 (0 = آمن تماماً، 100 = خطر حظر الحساب الإعلاني).\n"
            ."أجب بصيغة JSON فقط:\n"
            ."{\n"
            ."  \"is_compliant\": true,\n"
            ."  \"risk_score\": 15,\n"
            ."  \"flagged_phrases\": [\"العبارة المشبوهة إن وجدت\"],\n"
            ."  \"suggestions\": [\"اقتراح لتحسين النص وجعله مطابقاً للسياسات\"]\n"
            .'}';

        $userPrompt = "نص الإعلان المطلوب تدقيقه:\n{$adText}";

        $response = $this->aiManager->execute(
            action: AiUsageLog::ACTION_POLICY_CHECK,
            systemPrompt: $systemPrompt,
            userPrompt: $userPrompt,
            tenant: $tenant,
            affiliateId: $affiliateId
        );

        return $this->parseJson($response->content, [
            'is_compliant' => true,
            'risk_score' => 0,
            'flagged_phrases' => [],
            'suggestions' => [],
        ]);
    }

    /**
     * @template T of array
     *
     * @param  T  $default
     * @return T
     */
    protected function parseJson(string $jsonString, array $default): array
    {
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
