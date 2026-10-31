<?php

declare(strict_types=1);

namespace App\Livewire\Storefront;

use App\Domain\Customer\Services\CustomerOtpService;
use App\Models\Customer;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.storefront')]
class CustomerLoginPage extends Component
{
    public string $mode = 'otp'; // 'otp' or 'password'

    // OTP mode fields
    public string $phone = '';

    public string $otp = '';

    public bool $otpSent = false;

    // Password mode fields
    public string $email = '';

    public string $password = '';

    public ?string $errorMessage = null;

    public ?string $successMessage = null;

    public function setMode(string $mode): void
    {
        $this->mode = $mode;
        $this->errorMessage = null;
        $this->successMessage = null;
    }

    public function requestOtp(CustomerOtpService $otpService): void
    {
        $this->errorMessage = null;
        $this->successMessage = null;

        try {
            $otpService->requestOtp($this->phone);
            $this->otpSent = true;
            $this->successMessage = __('تم إرسال رمز التحقق في رسالة نصية. يرجى إدخال الرمز المكون من 6 أرقام.');
        } catch (ValidationException $e) {
            $err = $e->validator->errors()->first('phone');
            $this->errorMessage = $err !== '' ? $err : __('حدث خطأ أثناء إرسال رمز التحقق.');
        }
    }

    public function verifyOtp(CustomerOtpService $otpService): mixed
    {
        $this->errorMessage = null;

        try {
            $customer = $otpService->verifyOtp($this->phone, $this->otp);
            Auth::guard('customer')->login($customer, true);

            session()->flash('success', __('مرحباً بك! تم تسجيل الدخول بنجاح.'));

            return redirect()->intended(route('storefront.account.orders'));
        } catch (ValidationException $e) {
            $err = $e->validator->errors()->first('code');
            $this->errorMessage = $err !== '' ? $err : __('رمز التحقق غير صحيح.');

            return null;
        }
    }

    public function loginWithPassword(): mixed
    {
        $this->errorMessage = null;

        $this->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ], [
            'email.required' => __('يرجى إدخال البريد الإلكتروني.'),
            'email.email' => __('صيغة البريد الإلكتروني غير صالحة.'),
            'password.required' => __('يرجى إدخال كلمة المرور.'),
        ]);

        $tenantId = function_exists('tenancy') && tenancy()->initialized ? (string) tenant('id') : 'default';

        /** @var Customer|null $customer */
        $customer = Customer::query()
            ->where('tenant_id', $tenantId)
            ->where('email', strtolower(trim($this->email)))
            ->first();

        if (! $customer || ! $customer->password || ! password_verify($this->password, $customer->password)) {
            $this->errorMessage = __('البريد الإلكتروني أو كلمة المرور غير صحيحة.');

            return null;
        }

        if (! $customer->is_active) {
            $this->errorMessage = __('تم تعطيل هذا الحساب. يرجى التواصل مع إدارة المتجر.');

            return null;
        }

        Auth::guard('customer')->login($customer, true);

        session()->flash('success', __('تم تسجيل الدخول بنجاح.'));

        return redirect()->intended(route('storefront.account.orders'));
    }

    public function render(): View
    {
        return view('livewire.storefront.customer-login-page', [
            'pageTitle' => __('تسجيل الدخول'),
        ]);
    }
}
