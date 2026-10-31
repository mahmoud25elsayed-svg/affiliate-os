<?php

declare(strict_types=1);

namespace App\Http\Controllers\Store;

use App\Domain\Affiliate\Services\AffiliateKycService;
use App\Http\Controllers\Controller;
use App\Models\Affiliate;
use App\Models\User;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AffiliateKycDocumentController extends Controller
{
    public function show(
        Request $request,
        Affiliate $affiliate,
        string $side,
        AffiliateKycService $kycService
    ): BinaryFileResponse {
        /** @var User|null $user */
        $user = $request->user();

        if (! $user) {
            abort(401, __('يجب تسجيل الدخول أولاً'));
        }

        if (function_exists('tenancy') && tenancy()->initialized) {
            if ((string) $affiliate->tenant_id !== (string) tenant('id')) {
                abort(403, __('غير مصرح لك بعرض مستندات تابعة لمتجر آخر'));
            }
        } elseif ($user->tenant_id !== null && (string) $affiliate->tenant_id !== (string) $user->tenant_id) {
            abort(403, __('غير مصرح لك بعرض مستندات تابعة لمتجر آخر'));
        }

        if (! in_array($side, ['front', 'back'], true)) {
            abort(404, __('جهة مستند الهوية غير صالحة'));
        }

        $path = $kycService->viewDocument(
            affiliate: $affiliate,
            side: $side,
            viewer: $user,
            ip: $request->ip(),
            userAgent: $request->userAgent()
        );

        return response()->file($path);
    }
}
