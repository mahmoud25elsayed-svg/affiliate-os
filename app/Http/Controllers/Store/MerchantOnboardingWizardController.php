<?php

declare(strict_types=1);

namespace App\Http\Controllers\Store;

use App\Domain\Onboarding\Services\MerchantOnboardingProgressService;
use App\Domain\Onboarding\Services\TenantDemoDataService;
use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class MerchantOnboardingWizardController extends Controller
{
    public function __construct(
        protected MerchantOnboardingProgressService $onboardingService,
        protected TenantDemoDataService $demoDataService
    ) {}

    /**
     * Get real-time onboarding checklist progress and steps.
     */
    public function progress(Request $request): JsonResponse
    {
        /** @var Tenant $tenant */
        $tenant = Tenant::findOrFail((string) tenant('id'));

        $progress = $this->onboardingService->getProgress($tenant);
        $progress['has_demo_data'] = $this->demoDataService->hasDemoData($tenant);

        return response()->json($progress);
    }

    /**
     * Seed 5 sample products, 2 affiliates, and 3 orders.
     */
    public function seedDemo(Request $request): JsonResponse|RedirectResponse
    {
        /** @var Tenant $tenant */
        $tenant = Tenant::findOrFail((string) tenant('id'));

        $results = $this->demoDataService->seedDemoData($tenant);

        if ($request->wantsJson()) {
            return response()->json([
                'status' => 'success',
                'message' => __('تم إنشاء البيانات التجريبية بنجاح.'),
                'data' => $results,
            ]);
        }

        return redirect()->back()->with('success', __('تم إنشاء البيانات التجريبية للمتجر بنجاح.'));
    }

    /**
     * Purge all seeded demo data safely without touching real store records.
     */
    public function purgeDemo(Request $request): JsonResponse|RedirectResponse
    {
        /** @var Tenant $tenant */
        $tenant = Tenant::findOrFail((string) tenant('id'));

        $results = $this->demoDataService->purgeDemoData($tenant);

        if ($request->wantsJson()) {
            return response()->json([
                'status' => 'success',
                'message' => __('تم حذف البيانات التجريبية بنجاح.'),
                'data' => $results,
            ]);
        }

        return redirect()->back()->with('success', __('تم تنظيف وحذف كافة البيانات التجريبية بنجاح.'));
    }
}
