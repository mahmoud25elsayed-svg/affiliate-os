<?php

declare(strict_types=1);

namespace App\Http\Controllers\Store;

use App\Domain\AI\Exceptions\AiQuotaExceededException;
use App\Domain\AI\Exceptions\CustomerPiiDetectedException;
use App\Domain\AI\Services\MerchantAiService;
use App\Http\Controllers\Controller;
use App\Models\AiUsageLog;
use App\Models\Tenant;
use App\Support\Billing\Features;
use App\Support\Billing\Limits;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

class StoreAiToolsController extends Controller
{
    public function __construct(
        protected MerchantAiService $merchantAiService
    ) {}

    public function index(): View
    {
        /** @var Tenant $tenant */
        $tenant = tenant();
        Features::ensure('ai', $tenant);

        $limit = Limits::get('ai_requests_monthly', $tenant);
        $usage = Limits::usage('ai_requests_monthly', $tenant);

        $recentLogs = AiUsageLog::where('tenant_id', $tenant->id)
            ->latest()
            ->take(25)
            ->get();

        $totalTokensThisMonth = (int) AiUsageLog::where('tenant_id', $tenant->id)
            ->where('status', AiUsageLog::STATUS_SUCCESS)
            ->where('created_at', '>=', now()->startOfMonth())
            ->sum('total_tokens');

        return view('store.ai.index', [
            'tenant' => $tenant,
            'limit' => $limit,
            'usage' => $usage,
            'totalTokensThisMonth' => $totalTokensThisMonth,
            'recentLogs' => $recentLogs,
        ]);
    }

    public function generateProductCopy(Request $request): JsonResponse
    {
        /** @var Tenant $tenant */
        $tenant = tenant();
        Features::ensure('ai', $tenant);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'features' => ['nullable', 'string', 'max:1000'],
            'dialect' => ['required', 'string', 'in:egyptian,msa,english'],
            'tone' => ['required', 'string', 'in:persuasive,enthusiastic,professional,luxury'],
        ]);

        try {
            $result = $this->merchantAiService->generateProductCopy(
                title: $validated['title'],
                features: $validated['features'] ?? null,
                dialect: $validated['dialect'],
                tone: $validated['tone'],
                tenant: $tenant
            );

            return response()->json([
                'success' => true,
                'data' => $result,
            ]);
        } catch (CustomerPiiDetectedException $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 422);
        } catch (AiQuotaExceededException $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 429);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => __('حدث خطأ أثناء معالجة الطلب عبر الذكاء الاصطناعي: ').$e->getMessage(),
            ], 500);
        }
    }

    public function suggestTags(Request $request): JsonResponse
    {
        /** @var Tenant $tenant */
        $tenant = tenant();
        Features::ensure('ai', $tenant);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $result = $this->merchantAiService->suggestCategoriesAndTags(
                title: $validated['title'],
                description: $validated['description'] ?? null,
                tenant: $tenant
            );

            return response()->json([
                'success' => true,
                'data' => $result,
            ]);
        } catch (CustomerPiiDetectedException $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 422);
        } catch (AiQuotaExceededException $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 429);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => __('فشل توليد التصنيفات: ').$e->getMessage(),
            ], 500);
        }
    }

    public function translate(Request $request): JsonResponse
    {
        /** @var Tenant $tenant */
        $tenant = tenant();
        Features::ensure('ai', $tenant);

        $validated = $request->validate([
            'text' => ['required', 'string', 'max:2000'],
            'target_lang' => ['required', 'string', 'in:ar,en'],
        ]);

        try {
            $translation = $this->merchantAiService->translateContent(
                text: $validated['text'],
                targetLanguage: $validated['target_lang'],
                tenant: $tenant
            );

            return response()->json([
                'success' => true,
                'data' => [
                    'translation' => $translation,
                ],
            ]);
        } catch (CustomerPiiDetectedException $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 422);
        } catch (AiQuotaExceededException $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 429);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => __('فشلت الترجمة: ').$e->getMessage(),
            ], 500);
        }
    }
}
