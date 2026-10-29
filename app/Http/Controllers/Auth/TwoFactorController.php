<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Auth\TotpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TwoFactorController extends Controller
{
    public function __construct(
        protected TotpService $totpService
    ) {}

    public function showSetup(Request $request): View|RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->hasTwoFactorEnabled()) {
            return redirect('/platform');
        }

        $secret = session()->get('two_factor_setup_secret');
        if (! $secret) {
            $secret = $this->totpService->generateSecret();
            session()->put('two_factor_setup_secret', $secret);
        }

        $qrUrl = $this->totpService->getQrCodeUrl('AffiliateOS Platform', $user->email, $secret);

        return view('auth.2fa-setup', [
            'secret' => $secret,
            'qrUrl' => $qrUrl,
        ]);
    }

    public function confirmSetup(Request $request): RedirectResponse
    {
        $request->validate([
            'code' => ['required', 'string', 'digits:6'],
        ]);

        /** @var User $user */
        $user = $request->user();
        $secret = (string) session()->get('two_factor_setup_secret');

        if (! $secret || ! $this->totpService->verify($secret, (string) $request->input('code'))) {
            return back()->withErrors(['code' => 'رمز التحقق غير صحيح. تأكد من إدخال الرمز من تطبيق المصادقة.']);
        }

        $user->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_confirmed_at' => now(),
        ])->save();

        session()->forget('two_factor_setup_secret');
        session()->put('two_factor_authenticated', true);

        return redirect('/platform')->with('success', 'تم تفعيل المصادقة الثنائية بنجاح.');
    }

    public function showChallenge(): View
    {
        return view('auth.2fa-challenge');
    }

    public function verifyChallenge(Request $request): RedirectResponse
    {
        $request->validate([
            'code' => ['required', 'string', 'digits:6'],
        ]);

        /** @var User $user */
        $user = $request->user();

        if (! $user->two_factor_secret || ! $this->totpService->verify($user->two_factor_secret, (string) $request->input('code'))) {
            return back()->withErrors(['code' => 'رمز التحقق منتهي أو غير صحيح.']);
        }

        session()->put('two_factor_authenticated', true);

        return redirect()->intended('/platform');
    }
}
