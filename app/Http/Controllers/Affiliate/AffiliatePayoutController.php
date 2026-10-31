<?php

declare(strict_types=1);

namespace App\Http\Controllers\Affiliate;

use App\Domain\Affiliate\Services\AffiliatePayoutAccountService;
use App\Domain\Affiliate\Settings\AffiliateProgramSettings;
use App\Http\Controllers\Controller;
use App\Models\Affiliate;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use InvalidArgumentException;

class AffiliatePayoutController extends Controller
{
    public function __construct(
        protected AffiliatePayoutAccountService $payoutAccountService
    ) {}

    public function show(): View
    {
        /** @var Affiliate $affiliate */
        $affiliate = Auth::guard('affiliate')->user();
        $tenant = function_exists('tenancy') && tenancy()->initialized ? tenancy()->tenant : null;
        $settings = AffiliateProgramSettings::load();

        return view('affiliate.payout-settings', [
            'affiliate' => $affiliate,
            'storeName' => $tenant instanceof Tenant ? $tenant->name : __('متجرنا'),
            'settings' => $settings,
        ]);
    }

    public function requestOtp(): RedirectResponse
    {
        /** @var Affiliate $affiliate */
        $affiliate = Auth::guard('affiliate')->user();

        $this->payoutAccountService->requestChangeOtp($affiliate);

        return back()->with('success', __('تم إرسال رمز التحقق (OTP) إلى هاتفك المسجل.'));
    }

    public function update(Request $request): RedirectResponse
    {
        /** @var Affiliate $affiliate */
        $affiliate = Auth::guard('affiliate')->user();

        $request->validate([
            'payout_method' => ['required', 'string', 'in:wallet_phone,instapay,bank_account'],
            'otp_code' => ['required', 'string', 'size:6'],
        ], [
            'payout_method.required' => __('يرجى اختيار طريقة استلام الأرباح'),
            'otp_code.required' => __('رمز التحقق مطلوب لتأكيد التغيير'),
            'otp_code.size' => __('رمز التحقق يتكون من 6 أرقام'),
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

            return redirect()->route('affiliate.payout-settings.show')
                ->with('success', __('تم تحديث بيانات الدفع بنجاح. لأسباب أمنية، تم تجميد السحب مؤقتاً لمدة 24 ساعة.'));
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }
}
