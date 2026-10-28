<?php

declare(strict_types=1);

namespace App\Http\Controllers\Affiliate\App;

use App\Domain\Affiliate\Services\AffiliateKycService;
use App\Domain\Affiliate\Services\AffiliatePayoutAccountService;
use App\Domain\Affiliate\Settings\AffiliateProgramSettings;
use App\Http\Controllers\Controller;
use App\Models\Affiliate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

class AppProfileController extends Controller
{
    public function __construct(
        protected AffiliateKycService $kycService,
        protected AffiliatePayoutAccountService $payoutAccountService
    ) {}

    public function index(): Response
    {
        /** @var Affiliate $affiliate */
        $affiliate = Auth::guard('affiliate')->user();
        $settings = AffiliateProgramSettings::load();

        return Inertia::render('App/Profile', [
            'affiliate' => [
                'id' => $affiliate->id,
                'name' => $affiliate->name,
                'phone' => $affiliate->phone,
                'email' => $affiliate->email,
                'code' => $affiliate->code,
                'status' => $affiliate->status,
                'status_reason' => $affiliate->status_reason,
                'kyc_status' => $affiliate->kyc_status,
                'kyc_rejection_reason' => $affiliate->kyc_rejected_reason,
                'national_id_number' => $affiliate->national_id_number,
                'payout_method' => $affiliate->payout_method,
                'payout_details' => $affiliate->payout_details,
                'is_frozen' => $affiliate->isPayoutFrozen(),
                'frozen_until' => $affiliate->payouts_frozen_until?->format('Y-m-d H:i'),
                'created_at' => $affiliate->created_at?->format('Y-m-d') ?? '',
            ],
            'settings' => [
                'min_payout_minor' => $settings->min_payout_minor,
                'payout_days' => $settings->payout_days,
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        /** @var Affiliate $affiliate */
        $affiliate = Auth::guard('affiliate')->user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'current_password' => ['nullable', 'string'],
            'new_password' => ['nullable', 'string', 'min:8', 'confirmed'],
        ], [
            'name.required' => 'الاسم مطلوب',
            'new_password.min' => 'يجب ألا تقل كلمة المرور الجديدة عن 8 أحرف',
            'new_password.confirmed' => 'تأكيد كلمة المرور غير متطابق',
        ]);

        $affiliate->name = (string) $validated['name'];
        if (isset($validated['email'])) {
            $affiliate->email = (string) $validated['email'];
        }

        if (! empty($validated['new_password'])) {
            if (empty($validated['current_password']) || ! Hash::check((string) $validated['current_password'], (string) $affiliate->password)) {
                throw ValidationException::withMessages([
                    'current_password' => ['كلمة المرور الحالية غير صحيحة.'],
                ]);
            }

            $affiliate->password = Hash::make((string) $validated['new_password']);
        }

        $affiliate->save();

        return redirect()->route('affiliate.app.profile.index')->with('success', 'تم تحديث بيانات الحساب بنجاح.');
    }

    public function submitKyc(Request $request): RedirectResponse
    {
        /** @var Affiliate $affiliate */
        $affiliate = Auth::guard('affiliate')->user();

        $validated = $request->validate([
            'national_id_number' => ['required', 'string', 'min:10', 'max:20'],
            'id_card_front' => ['required', 'file', 'image', 'max:5120'],
            'id_card_back' => ['required', 'file', 'image', 'max:5120'],
        ], [
            'national_id_number.required' => 'الرقم القومي مطلوب',
            'id_card_front.required' => 'صورة وجه البطاقة مطلوبة',
            'id_card_back.required' => 'صورة ظهر البطاقة مطلوبة',
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

            return redirect()->route('affiliate.app.profile.index')
                ->with('success', 'تم رفع وثائق الهوية بنجاح! سيتم مراجعتها من قِبل إدارة المتجر.');
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function requestPayoutOtp(): RedirectResponse
    {
        /** @var Affiliate $affiliate */
        $affiliate = Auth::guard('affiliate')->user();

        $this->payoutAccountService->requestChangeOtp($affiliate);

        return back()->with('success', 'تم إرسال رمز التحقق (OTP) إلى هاتفك المسجل.');
    }

    public function updatePayoutSettings(Request $request): RedirectResponse
    {
        /** @var Affiliate $affiliate */
        $affiliate = Auth::guard('affiliate')->user();

        $request->validate([
            'payout_method' => ['required', 'string', 'in:wallet_phone,instapay,bank_account'],
            'otp_code' => ['required', 'string', 'size:6'],
        ], [
            'payout_method.required' => 'يرجى اختيار طريقة استلام الأرباح',
            'otp_code.required' => 'رمز التحقق مطلوب لتأكيد التغيير',
            'otp_code.size' => 'رمز التحقق يتكون من 6 أرقام',
        ]);

        $method = (string) $request->input('payout_method');
        $otpCode = (string) $request->input('otp_code');

        $details = match ($method) {
            Affiliate::PAYOUT_METHOD_WALLET => [
                'wallet_provider' => $request->input('wallet_provider', 'vodafone_cash'),
                'phone' => $request->input('wallet_phone'),
            ],
            Affiliate::PAYOUT_METHOD_INSTAPAY => [
                'instapay_handle' => $request->input('instapay_handle'),
            ],
            Affiliate::PAYOUT_METHOD_BANK => [
                'bank_name' => $request->input('bank_name'),
                'account_holder_name' => $request->input('account_holder_name'),
                'iban' => $request->input('iban'),
                'swift_code' => $request->input('swift_code'),
            ],
            default => [],
        };

        try {
            $this->payoutAccountService->updatePayoutDetails($affiliate, $method, $details, $otpCode);

            return redirect()->route('affiliate.app.profile.index')
                ->with('success', 'تم تحديث بيانات استلام الأرباح بنجاح. لأسباب أمنية تم تجميد السحب مؤقتاً لمدة 24 ساعة.');
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }
}
