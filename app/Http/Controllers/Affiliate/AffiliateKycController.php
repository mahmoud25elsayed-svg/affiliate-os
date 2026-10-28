<?php

declare(strict_types=1);

namespace App\Http\Controllers\Affiliate;

use App\Domain\Affiliate\Services\AffiliateKycService;
use App\Http\Controllers\Controller;
use App\Models\Affiliate;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use InvalidArgumentException;

class AffiliateKycController extends Controller
{
    public function __construct(
        protected AffiliateKycService $kycService
    ) {}

    public function show(): View
    {
        /** @var Affiliate $affiliate */
        $affiliate = Auth::guard('affiliate')->user();
        $tenant = function_exists('tenancy') && tenancy()->initialized ? tenancy()->tenant : null;

        return view('affiliate.kyc', [
            'affiliate' => $affiliate,
            'storeName' => $tenant instanceof Tenant ? $tenant->name : 'متجرنا',
        ]);
    }

    public function submit(Request $request): RedirectResponse
    {
        /** @var Affiliate $affiliate */
        $affiliate = Auth::guard('affiliate')->user();

        $validated = $request->validate([
            'national_id_number' => ['required', 'string', 'min:10', 'max:20'],
            'id_card_front' => ['required', 'file', 'image', 'max:5120'], // 5MB max
            'id_card_back' => ['required', 'file', 'image', 'max:5120'],
        ], [
            'national_id_number.required' => 'الرقم القومي مطلوب',
            'id_card_front.required' => 'صورة وجه البطاقة مطلوبة',
            'id_card_front.image' => 'يجب أن يكون الملف صورة (JPEG, PNG)',
            'id_card_back.required' => 'صورة ظهر البطاقة مطلوبة',
            'id_card_back.image' => 'يجب أن يكون الملف صورة (JPEG, PNG)',
        ]);

        /** @var UploadedFile $front */
        $front = $validated['id_card_front'];
        /** @var UploadedFile $back */
        $back = $validated['id_card_back'];

        try {
            $this->kycService->submitDocuments(
                $affiliate,
                (string) $validated['national_id_number'],
                $front,
                $back
            );

            return redirect()->route('affiliate.kyc.show')
                ->with('success', 'تم رفع وثائق الهوية بنجاح! جاري مراجعتها من قِبل إدارة المتجر.');
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }
}
