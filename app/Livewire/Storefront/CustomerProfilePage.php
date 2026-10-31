<?php

declare(strict_types=1);

namespace App\Livewire\Storefront;

use App\Domain\Customer\Services\CustomerAnonymizationService;
use App\Models\Customer;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.storefront')]
class CustomerProfilePage extends Component
{
    public string $first_name = '';

    public string $last_name = '';

    public string $email = '';

    public string $current_password = '';

    public string $new_password = '';

    public string $new_password_confirmation = '';

    public bool $confirmDelete = false;

    public ?string $successMessage = null;

    public ?string $passwordSuccessMessage = null;

    public ?string $errorMessage = null;

    public function mount(): void
    {
        /** @var Customer|null $customer */
        $customer = Auth::guard('customer')->user();
        if ($customer) {
            $this->first_name = $customer->first_name;
            $this->last_name = $customer->last_name ?? '';
            $this->email = $customer->email ?? '';
        }
    }

    public function updateProfile(): void
    {
        /** @var Customer|null $customer */
        $customer = Auth::guard('customer')->user();
        if (! $customer) {
            abort(403);
        }

        $this->validate([
            'first_name' => 'required|string|max:100',
            'last_name' => 'nullable|string|max:100',
            'email' => 'nullable|email|max:255',
        ], [
            'first_name.required' => __('يرجى إدخال الاسم الأول.'),
            'email.email' => __('البريد الإلكتروني غير صالح.'),
        ]);

        $customer->update([
            'first_name' => trim($this->first_name),
            'last_name' => trim($this->last_name) !== '' ? trim($this->last_name) : null,
            'email' => trim($this->email) !== '' ? strtolower(trim($this->email)) : null,
        ]);

        $this->successMessage = __('تم تحديث البيانات الشخصية بنجاح.');
    }

    public function updatePassword(): void
    {
        /** @var Customer|null $customer */
        $customer = Auth::guard('customer')->user();
        if (! $customer) {
            abort(403);
        }

        $this->validate([
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:8|confirmed',
        ], [
            'current_password.required' => __('يرجى إدخال كلمة المرور الحالية.'),
            'new_password.required' => __('يرجى إدخال كلمة المرور الجديدة.'),
            'new_password.min' => __('يجب ألا تقل كلمة المرور عن 8 أحرف.'),
            'new_password.confirmed' => __('تأكيد كلمة المرور غير متطابق.'),
        ]);

        if ($customer->password && ! Hash::check($this->current_password, $customer->password)) {
            $this->addError('current_password', __('كلمة المرور الحالية غير صحيحة.'));

            return;
        }

        $customer->update([
            'password' => Hash::make($this->new_password),
        ]);

        $this->reset(['current_password', 'new_password', 'new_password_confirmation']);
        $this->passwordSuccessMessage = __('تم تغيير كلمة المرور بنجاح.');
    }

    public function deleteAccount(CustomerAnonymizationService $anonymizationService): mixed
    {
        /** @var Customer|null $customer */
        $customer = Auth::guard('customer')->user();
        if (! $customer) {
            abort(403);
        }

        if (! $this->confirmDelete) {
            $this->errorMessage = __('يرجى تأكيد رغبتك في حذف الحساب عبر تحديد المربع.');

            return null;
        }

        $anonymizationService->anonymize($customer);

        session()->flash('success', __('تم حذف حسابك وإخفاء بياناتك الشخصية بنجاح.'));

        return redirect()->route('storefront.home');
    }

    public function render(): View
    {
        return view('livewire.storefront.customer-profile-page', [
            'pageTitle' => __('الملف الشخصي'),
        ]);
    }
}
