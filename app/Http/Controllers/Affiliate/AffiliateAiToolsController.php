<?php

declare(strict_types=1);

namespace App\Http\Controllers\Affiliate;

use App\Domain\AI\Exceptions\AiQuotaExceededException;
use App\Domain\AI\Exceptions\CustomerPiiDetectedException;
use App\Domain\AI\Services\AffiliateAiService;
use App\Http\Controllers\Controller;
use App\Models\Affiliate;
use App\Models\AiUsageLog;
use App\Models\Tenant;
use App\Support\Billing\Features;
use App\Support\Billing\Limits;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Throwable;

class AffiliateAiToolsController extends Controller
{
    public function __construct(
        protected AffiliateAiService $affiliateAiService
    ) {}

    public function index(): View
    {
        /** @var Tenant $tenant */
        $tenant = tenant();
        Features::ensure('ai', $tenant);

        $limit = Limits::get('ai_requests_monthly', $tenant);
        $usage = Limits::usage('ai_requests_monthly', $tenant);

        /** @var Affiliate|null $affiliate */
        $affiliate = Auth::guard('affiliate')->user();

        $recentLogs = AiUsageLog::where('tenant_id', $tenant->id)
            ->where('affiliate_id', $affiliate?->id)
            ->latest()
            ->take(15)
            ->get();

        return view('affiliate.ai.index', [
            'tenant' => $tenant,
            'limit' => $limit,
            'usage' => $usage,
            'affiliate' => $affiliate,
            'recentLogs' => $recentLogs,
        ]);
    }

    public function generateAdCopy(Request $request): JsonResponse
    {
        /** @var Tenant $tenant */
        $tenant = tenant();
        Features::ensure('ai', $tenant);

        $validated = $request->validate([
            'product_name' => ['required', 'string', 'max:255'],
            'price' => ['required', 'string', 'max:50'],
            'platform' => ['required', 'string', 'in:facebook,tiktok'],
            'tone' => ['required', 'string', 'in:enthusiastic,problem_solving,urgency,storytelling'],
            'target_audience' => ['nullable', 'string', 'max:255'],
        ]);

        /** @var Affiliate|null $affiliate */
        $affiliate = Auth::guard('affiliate')->user();

        try {
            $result = $this->affiliateAiService->generateAdCopy(
                productName: $validated['product_name'],
                price: $validated['price'],
                platform: $validated['platform'],
                tone: $validated['tone'],
                targetAudience: $validated['target_audience'] ?? null,
                affiliateId: $affiliate?->id,
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
                'error' => __('حدث خطأ أثناء كتابة نصوص الإعلانات: ').$e->getMessage(),
            ], 500);
        }
    }

    public function suggestAngles(Request $request): JsonResponse
    {
        /** @var Tenant $tenant */
        $tenant = tenant();
        Features::ensure('ai', $tenant);

        $validated = $request->validate([
            'product_name' => ['required', 'string', 'max:255'],
            'target_audience' => ['nullable', 'string', 'max:255'],
        ]);

        /** @var Affiliate|null $affiliate */
        $affiliate = Auth::guard('affiliate')->user();

        try {
            $result = $this->affiliateAiService->suggestMarketingAngles(
                productName: $validated['product_name'],
                targetAudience: $validated['target_audience'] ?? null,
                affiliateId: $affiliate?->id,
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
                'error' => __('فشل توليد زوايا التسويق: ').$e->getMessage(),
            ], 500);
        }
    }

    public function checkPolicy(Request $request): JsonResponse
    {
        /** @var Tenant $tenant */
        $tenant = tenant();
        Features::ensure('ai', $tenant);

        $validated = $request->validate([
            'ad_text' => ['required', 'string', 'max:3000'],
        ]);

        /** @var Affiliate|null $affiliate */
        $affiliate = Auth::guard('affiliate')->user();

        try {
            $result = $this->affiliateAiService->checkPolicyCompliance(
                adText: $validated['ad_text'],
                affiliateId: $affiliate?->id,
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
                'error' => __('فحص السياسات غير متاح مؤقتاً: ').$e->getMessage(),
            ], 500);
        }
    }
}
