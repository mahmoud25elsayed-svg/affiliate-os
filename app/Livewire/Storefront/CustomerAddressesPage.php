<?php

declare(strict_types=1);

namespace App\Livewire\Storefront;

use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Support\Location\Governorate;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.storefront')]
class CustomerAddressesPage extends Component
{
    public bool $showAddModal = false;

    public string $name = '';

    public string $phone = '';

    public ?int $governorate_id = null;

    public string $city = '';

    public string $address_line = '';

    public ?string $notes = null;

    public bool $is_default = false;

    public ?string $successMessage = null;

    /**
     * @return Collection<int, CustomerAddress>
     */
    public function getAddressesProperty(): Collection
    {
        /** @var Customer|null $customer */
        $customer = Auth::guard('customer')->user();
        if (! $customer) {
            return new Collection;
        }

        return $customer->addresses()->with('governorate')->latest()->get();
    }

    /**
     * @return Collection<int, Governorate>
     */
    public function getGovernoratesProperty(): Collection
    {
        return Governorate::query()->orderBy('name_ar')->get();
    }

    public function saveAddress(): void
    {
        /** @var Customer|null $customer */
        $customer = Auth::guard('customer')->user();
        if (! $customer) {
            abort(403);
        }

        $this->validate([
            'name' => 'required|string|max:100',
            'phone' => 'required|string|max:20',
            'governorate_id' => 'required|exists:governorates,id',
            'city' => 'required|string|max:100',
            'address_line' => 'required|string|max:500',
            'notes' => 'nullable|string|max:500',
        ], [
            'name.required' => 'يرجى إدخال اسم المستلم.',
            'phone.required' => 'يرجى إدخال رقم الهاتف.',
            'governorate_id.required' => 'يرجى اختيار المحافظة.',
            'city.required' => 'يرجى إدخال المدينة / المنطقة.',
            'address_line.required' => 'يرجى إدخال العنوان بالتفصيل.',
        ]);

        $tenantId = function_exists('tenancy') && tenancy()->initialized ? (string) tenant('id') : 'default';

        $isFirst = $customer->addresses()->count() === 0;
        $shouldBeDefault = $this->is_default || $isFirst;

        if ($shouldBeDefault) {
            CustomerAddress::query()
                ->where('customer_id', $customer->id)
                ->update(['is_default' => false]);
        }

        CustomerAddress::create([
            'tenant_id' => $tenantId,
            'customer_id' => $customer->id,
            'name' => trim($this->name),
            'phone' => trim($this->phone),
            'governorate_id' => (int) $this->governorate_id,
            'city' => trim($this->city),
            'address_line' => trim($this->address_line),
            'notes' => $this->notes ? trim($this->notes) : null,
            'is_default' => $shouldBeDefault,
        ]);

        $this->reset(['name', 'phone', 'governorate_id', 'city', 'address_line', 'notes', 'is_default', 'showAddModal']);
        $this->successMessage = 'تمت إضافة العنوان بنجاح.';
    }

    public function setAsDefault(int $addressId): void
    {
        /** @var Customer|null $customer */
        $customer = Auth::guard('customer')->user();
        if (! $customer) {
            abort(403);
        }

        /** @var CustomerAddress|null $address */
        $address = CustomerAddress::query()
            ->where('customer_id', $customer->id)
            ->where('id', $addressId)
            ->first();

        if ($address) {
            $address->setAsDefault();
            $this->successMessage = 'تم تعيين العنوان الافتراضي بنجاح.';
        }
    }

    public function deleteAddress(int $addressId): void
    {
        /** @var Customer|null $customer */
        $customer = Auth::guard('customer')->user();
        if (! $customer) {
            abort(403);
        }

        CustomerAddress::query()
            ->where('customer_id', $customer->id)
            ->where('id', $addressId)
            ->delete();

        $this->successMessage = 'تم حذف العنوان بنجاح.';
    }

    public function render(): View
    {
        return view('livewire.storefront.customer-addresses-page', [
            'addresses' => $this->getAddressesProperty(),
            'governorates' => $this->getGovernoratesProperty(),
            'pageTitle' => 'عناويني',
        ]);
    }
}
