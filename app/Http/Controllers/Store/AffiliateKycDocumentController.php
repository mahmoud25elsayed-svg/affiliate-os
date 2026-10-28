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
            abort(401, 'يجب تسجيل الدخول أولاً');
        }

        if (! in_array($side, ['front', 'back'], true)) {
            abort(404, 'جهة مستند الهوية غير صالحة');
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
