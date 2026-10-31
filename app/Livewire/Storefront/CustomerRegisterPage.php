<?php

declare(strict_types=1);

namespace App\Livewire\Storefront;

use App\Models\Customer;
use App\Support\Phone\PhoneNumber;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.storefront')]
class CustomerRegisterPage extends Component
{
    public string $first_name = '';

    public string $last_name = '';

    public string $phone = '';

    public string $email = '';

    public string $password = '';

    public string $password_confirmation = '';

    public ?string $errorMessage = null;

    public function register(): mixed
    {
        $this->errorMessage = null;

        $this->validate([
            'first_name' => 'required|string|max:100',
            'last_name' => 'nullable|string|max:100',
            'phone' => 'required|string',
            'email' => 'required|email|max:255',
            'password' => 'required|string|min:8|confirmed',
        ], [
            'first_name.required' => 'يرجى إدخال الاسم الأول.',
            'phone.required' => 'يرجى إدخال رقم الهاتف.',
            'email.required' => 'يرجى إدخال البريد الإلكتروني.',
            'email.email' => 'البريد الإلكتروني غير صالح.',
            'password.required' => 'يرجى إدخال كلمة المرور.',
            'password.min' => 'يجب ألا تقل كلمة المرور عن 8 أحرف.',
            'password.confirmed' => 'تأكيد كلمة المرور غير متطابق.',
        ]);

        $parsedPhone = PhoneNumber::tryParse($this->phone);
        if ($parsedPhone === null) {
            $this->addError('phone', PhoneNumber::invalidMessage());

            return null;
        }

        $tenantId = function_exists('tenancy') && tenancy()->initialized ? (string) tenant('id') : 'default';

        // Check unique email and phone within this tenant
        $existingEmail = Customer::query()
            ->where('tenant_id', $tenantId)
            ->where('email', strtolower(trim($this->email)))
            ->exists();

        if ($existingEmail) {
            $this->addError('email', 'البريد الإلكتروني مسجل مسبقاً في هذا المتجر.');

            return null;
        }

        $existingPhone = Customer::query()
            ->where('tenant_id', $tenantId)
            ->where('phone_hash', $parsedPhone->hash())
            ->exists();

        if ($existingPhone) {
            $this->addError('phone', 'رقم الهاتف مسجل مسبقاً في هذا المتجر.');

            return null;
        }

        /** @var Customer $customer */
        $customer = Customer::create([
            'tenant_id' => $tenantId,
            'first_name' => trim($this->first_name),
            'last_name' => trim($this->last_name) !== '' ? trim($this->last_name) : null,
            'email' => strtolower(trim($this->email)),
            'phone_e164' => $parsedPhone->e164,
            'phone_hash' => $parsedPhone->hash(),
            'password' => Hash::make($this->password),
            'is_active' => true,
        ]);

        Auth::guard('customer')->login($customer, true);

        session()->flash('success', 'تم إنشاء الحساب بنجاح. أهلاً بك!');

        return redirect()->route('storefront.account.orders');
    }

    public function render(): View
    {
        return view('livewire.storefront.customer-register-page', [
            'pageTitle' => 'إنشاء حساب جديد',
        ]);
    }
}
