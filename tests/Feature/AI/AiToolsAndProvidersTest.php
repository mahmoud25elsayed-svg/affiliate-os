<?php

declare(strict_types=1);

namespace Tests\Feature\AI;

use App\Domain\AI\Exceptions\CustomerPiiDetectedException;
use App\Domain\AI\Providers\ClaudeAiProvider;
use App\Domain\AI\Providers\FakeAiProvider;
use App\Domain\AI\Services\CustomerDataSanitizer;
use App\Models\Affiliate;
use App\Models\AiUsageLog;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy\Permissions\TenantPermissionMatrix;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpClientRequest;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiToolsAndProvidersTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected User $owner;

    protected Affiliate $affiliate;

    protected Plan $aiPlan;

    protected Plan $noAiPlan;

    protected string $domain = 'ai-store.test';

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Create Plans
        $this->aiPlan = Plan::create([
            'slug' => 'ai-enterprise-plan',
            'name_ar' => 'باقة الذكاء الاصطناعي',
            'name_en' => 'AI Plan',
            'price_monthly' => 199900,
            'price_yearly' => 1999000,
            'limits' => [
                'ai_requests_monthly' => 5, // small limit for testing quota
            ],
            'features' => [
                'ai' => true,
                'affiliate' => true,
            ],
            'is_active' => true,
        ]);

        $this->noAiPlan = Plan::create([
            'slug' => 'no-ai-plan',
            'name_ar' => 'باقة بدون ذكاء اصطناعي',
            'name_en' => 'No AI Plan',
            'price_monthly' => 29900,
            'price_yearly' => 299000,
            'limits' => [
                'ai_requests_monthly' => 0,
            ],
            'features' => [
                'ai' => false,
                'affiliate' => true,
            ],
            'is_active' => true,
        ]);

        // 2. Create Tenant with domain
        $this->tenant = Tenant::create([
            'id' => 'ai-tenant',
            'name' => 'متجر الذكاء الاصطناعي',
        ]);
        $this->tenant->domains()->create(['domain' => $this->domain]);

        // 3. Create Subscription with AI enabled
        Subscription::create([
            'tenant_id' => $this->tenant->id,
            'plan_id' => $this->aiPlan->id,
            'status' => 'active',
            'starts_at' => now()->subDay(),
            'current_period_ends_at' => now()->addMonth(),
        ]);

        // 4. Create Owner
        $this->owner = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email' => 'owner@ai-store.test',
        ]);
        TenantPermissionMatrix::seedForTenant($this->tenant->id);
        TenantPermissionMatrix::assignRole($this->owner, TenantPermissionMatrix::ROLE_OWNER, $this->tenant->id);

        // 5. Create Affiliate
        $this->tenant->run(function () {
            $this->affiliate = Affiliate::create([
                'tenant_id' => $this->tenant->id,
                'name' => 'أحمد أفلييت',
                'phone' => '+201099887766',
                'email' => 'media_buyer@affiliate.test',
                'password' => bcrypt('secret123'),
                'code' => 'AFF-AI-10',
                'status' => Affiliate::STATUS_ACTIVE,
            ]);
        });
    }

    public function test_fake_ai_provider_generates_structured_responses(): void
    {
        $fake = new FakeAiProvider;
        $response = $fake->generate('System prompt for product_copy', 'User title: شاحن لاسلكي مغناطيسي');

        $this->assertSame('fake', $response->provider);
        $this->assertNotEmpty($response->content);
        $this->assertGreaterThan(0, $response->totalTokens);

        $decoded = json_decode($response->content, true);
        $this->assertIsArray($decoded);
        $this->assertArrayHasKey('headline', $decoded);
        $this->assertArrayHasKey('seo_title', $decoded);
    }

    public function test_claude_provider_sends_correct_payload_and_parses_response(): void
    {
        Http::fake([
            'https://api.anthropic.com/v1/messages' => Http::response([
                'id' => 'msg_test_123',
                'type' => 'message',
                'role' => 'assistant',
                'content' => [
                    [
                        'type' => 'text',
                        'text' => '{"headline":"سماعة رأس راقية","description":"وصف تفصيلي","bullet_points":["ميزة 1"],"seo_title":"سماعة رأس","seo_description":"وصف سيو"}',
                    ],
                ],
                'model' => 'claude-3-5-haiku-20241022',
                'usage' => [
                    'input_tokens' => 150,
                    'output_tokens' => 300,
                ],
            ], 200),
        ]);

        $claude = new ClaudeAiProvider(
            apiKey: 'sk-ant-test-key-sample',
            model: 'claude-3-5-haiku-20241022'
        );

        $response = $claude->generate('system instruction', 'write product description');

        $this->assertSame('claude', $response->provider);
        $this->assertSame('claude-3-5-haiku-20241022', $response->model);
        $this->assertSame(150, $response->promptTokens);
        $this->assertSame(300, $response->completionTokens);
        $this->assertSame(450, $response->totalTokens);
        $this->assertStringContainsString('سماعة رأس راقية', $response->content);

        // Verify request structure
        Http::assertSent(function (HttpClientRequest $req) {
            return $req->url() === 'https://api.anthropic.com/v1/messages'
                && $req->hasHeader('x-api-key', 'sk-ant-test-key-sample')
                && $req->hasHeader('anthropic-version', '2023-06-01')
                && $req->data()['model'] === 'claude-3-5-haiku-20241022'
                && $req->data()['system'] === 'system instruction'
                && $req->data()['messages'][0]['content'] === 'write product description';
        });
    }

    public function test_customer_pii_sanitizer_blocks_customer_phone_and_email(): void
    {
        $sanitizer = new CustomerDataSanitizer;

        // Valid product content without customer PII
        $sanitizer->assertNoCustomerPii('سماعة بلوتوث تدوم 24 ساعة وسعرها 450 جنيه');

        // Customer Egyptian phone number detected
        $this->expectException(CustomerPiiDetectedException::class);
        $sanitizer->assertNoCustomerPii('يرجى شحن المنتج إلى العميل على هاتف 01012345678 في أسرع وقت');
    }

    public function test_customer_pii_sanitizer_blocks_customer_email(): void
    {
        $sanitizer = new CustomerDataSanitizer;

        $this->expectException(CustomerPiiDetectedException::class);
        $sanitizer->assertNoCustomerPii('تفاصيل العميل: ahmed.mohamed@gmail.com');
    }

    public function test_ai_feature_flag_is_enforced(): void
    {
        // Switch subscription to a plan without AI
        Subscription::where('tenant_id', $this->tenant->id)->update([
            'plan_id' => $this->noAiPlan->id,
        ]);

        $this->actingAs($this->owner);

        // Attempt to access Store AI dashboard
        $res = $this->get("http://{$this->domain}/store/ai");
        $res->assertStatus(403);
    }

    public function test_monthly_quota_limit_is_enforced(): void
    {
        $this->actingAs($this->owner);

        // Limit is 5 requests per month. Simulate 5 successful logs in the current month:
        for ($i = 0; $i < 5; $i++) {
            AiUsageLog::create([
                'tenant_id' => $this->tenant->id,
                'user_id' => $this->owner->id,
                'action' => AiUsageLog::ACTION_PRODUCT_COPY,
                'provider' => 'fake',
                'model' => 'fake-model',
                'prompt_tokens' => 100,
                'completion_tokens' => 200,
                'total_tokens' => 300,
                'estimated_cost_cents' => 1,
                'status' => AiUsageLog::STATUS_SUCCESS,
                'created_at' => now(),
            ]);
        }

        // 6th request must be rejected with 429
        $res = $this->postJson("http://{$this->domain}/store/ai/generate-product-copy", [
            'title' => 'سماعة رأس رياضية',
            'dialect' => 'egyptian',
            'tone' => 'persuasive',
        ]);

        $res->assertStatus(429);
        $res->assertJsonFragment(['success' => false]);
        $this->assertStringContainsString('استنفذت الحصة الشهرية', $res->json('error'));
    }

    public function test_merchant_can_generate_product_copy_and_logs_usage(): void
    {
        $this->actingAs($this->owner);

        $res = $this->postJson("http://{$this->domain}/store/ai/generate-product-copy", [
            'title' => 'قلاية هوائية بدون زيت 5 لتر',
            'features' => 'شاشة لمس رقمية، 8 برامج طهي، وعاء غير لاصق',
            'dialect' => 'egyptian',
            'tone' => 'persuasive',
        ]);

        $res->assertStatus(200);
        $res->assertJsonPath('success', true);
        $this->assertNotEmpty($res->json('data.headline'));
        $this->assertNotEmpty($res->json('data.seo_title'));

        // Verify that usage log was recorded with tenant scoping
        $this->assertDatabaseHas('ai_usage_logs', [
            'tenant_id' => $this->tenant->id,
            'action' => AiUsageLog::ACTION_PRODUCT_COPY,
            'status' => AiUsageLog::STATUS_SUCCESS,
        ]);
    }

    public function test_merchant_can_suggest_tags_and_translate_content(): void
    {
        $this->actingAs($this->owner);

        // 1. Suggest tags
        $tagRes = $this->postJson("http://{$this->domain}/store/ai/suggest-tags", [
            'title' => 'ساعة يد ذكية مقاومة للماء',
            'description' => 'ساعة تتبع اللياقة ونبضات القلب والخطوات',
        ]);
        $tagRes->assertStatus(200);
        $tagRes->assertJsonPath('success', true);
        $this->assertIsArray($tagRes->json('data.categories'));
        $this->assertIsArray($tagRes->json('data.tags'));

        // 2. Translate content
        $transRes = $this->postJson("http://{$this->domain}/store/ai/translate", [
            'text' => 'أفضل عطر فرنسي رجالي يدوم طويلاً',
            'target_lang' => 'en',
        ]);
        $transRes->assertStatus(200);
        $transRes->assertJsonPath('success', true);
        $this->assertNotEmpty($transRes->json('data.translation'));
    }

    public function test_affiliate_can_generate_ad_copy_angles_and_check_policy(): void
    {
        $this->actingAs($this->affiliate, 'affiliate');

        // 1. Generate Ad Copy
        $adRes = $this->postJson("http://{$this->domain}/partners/ai/generate-ad-copy", [
            'product_name' => 'حذاء رياضي خفيف ومريح للمشي',
            'price' => '499 ج.م',
            'platform' => 'facebook',
            'tone' => 'problem_solving',
            'target_audience' => 'الشباب وممارسو رياضة الجري',
        ]);
        $adRes->assertStatus(200);
        $adRes->assertJsonPath('success', true);
        $this->assertNotEmpty($adRes->json('data.variants'));

        // 2. Suggest Marketing Angles
        $anglesRes = $this->postJson("http://{$this->domain}/partners/ai/suggest-angles", [
            'product_name' => 'حذاء رياضي',
            'target_audience' => 'الشباب',
        ]);
        $anglesRes->assertStatus(200);
        $anglesRes->assertJsonPath('success', true);
        $this->assertNotEmpty($anglesRes->json('data.angles'));

        // 3. Check Policy Compliance
        $policyRes = $this->postJson("http://{$this->domain}/partners/ai/check-policy", [
            'ad_text' => 'اشتري هذا الحذاء وتمتع بالراحة طوال اليوم مع دفع عند الاستلام',
        ]);
        $policyRes->assertStatus(200);
        $policyRes->assertJsonPath('success', true);
        $this->assertArrayHasKey('is_compliant', $policyRes->json('data'));
        $this->assertArrayHasKey('risk_score', $policyRes->json('data'));
    }

    public function test_customer_pii_is_blocked_on_merchant_endpoint(): void
    {
        $this->actingAs($this->owner);

        $res = $this->postJson("http://{$this->domain}/store/ai/generate-product-copy", [
            'title' => 'شاحن سيارة',
            'features' => 'يرجى الاتصال بالعميل على رقم 01234567890 لتأكيد مواصفات الشاحن',
            'dialect' => 'egyptian',
            'tone' => 'persuasive',
        ]);

        $res->assertStatus(422);
        $res->assertJsonFragment(['success' => false]);
        $this->assertStringContainsString('PII', $res->json('error'));
    }
}
