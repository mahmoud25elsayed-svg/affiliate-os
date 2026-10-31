<div class="py-10 bg-gray-50 min-h-[75vh]">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="mb-8 flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">حسابي</h1>
                <p class="text-sm text-gray-500 mt-1">إدارة عناوين التوصيل المسجلة</p>
            </div>
            <button type="button" wire:click="$toggle('showAddModal')"
                class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white font-medium text-sm rounded-xl transition-colors flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                <span>إضافة عنوان جديد</span>
            </button>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
            <!-- Sidebar Navigation -->
            <div class="md:col-span-1">
                <nav class="bg-white rounded-2xl shadow-sm border border-gray-100 p-2 space-y-1">
                    <a href="{{ route('storefront.account.orders') }}"
                        class="flex items-center gap-3 px-4 py-2.5 rounded-xl text-sm font-medium text-gray-600 hover:bg-gray-50 hover:text-gray-900 transition-colors">
                        <svg class="w-5 h-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                        </svg>
                        <span>طلباتي</span>
                    </a>
                    <a href="{{ route('storefront.account.addresses') }}"
                        class="flex items-center gap-3 px-4 py-2.5 rounded-xl text-sm font-semibold bg-emerald-50 text-emerald-800">
                        <svg class="w-5 h-5 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                        <span>عناويني</span>
                    </a>
                    <a href="{{ route('storefront.account.wishlist') }}"
                        class="flex items-center gap-3 px-4 py-2.5 rounded-xl text-sm font-medium text-gray-600 hover:bg-gray-50 hover:text-gray-900 transition-colors">
                        <svg class="w-5 h-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
                        </svg>
                        <span>المفضلة</span>
                    </a>
                    <a href="{{ route('storefront.account.profile') }}"
                        class="flex items-center gap-3 px-4 py-2.5 rounded-xl text-sm font-medium text-gray-600 hover:bg-gray-50 hover:text-gray-900 transition-colors">
                        <svg class="w-5 h-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                        </svg>
                        <span>الملف الشخصي</span>
                    </a>
                </nav>
            </div>

            <!-- Addresses Content -->
            <div class="md:col-span-3 space-y-6">
                @if ($successMessage)
                    <div class="p-4 bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm rounded-xl">
                        {{ $successMessage }}
                    </div>
                @endif

                <!-- Add Address Form/Modal -->
                @if ($showAddModal)
                    <div class="bg-white rounded-2xl shadow-sm border border-emerald-200 p-6 space-y-4">
                        <div class="flex items-center justify-between pb-3 border-b border-gray-100">
                            <h2 class="text-base font-bold text-gray-900">إضافة عنوان جديد</h2>
                            <button type="button" wire:click="$set('showAddModal', false)" class="text-gray-400 hover:text-gray-600">
                                ✕
                            </button>
                        </div>

                        <form wire:submit.prevent="saveAddress" class="space-y-4">
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-semibold text-gray-700 mb-1">اسم المستلم <span class="text-red-500">*</span></label>
                                    <input type="text" wire:model="name" placeholder="محمد أحمد"
                                        class="w-full px-3.5 py-2 rounded-xl border border-gray-300 text-sm focus:ring-2 focus:ring-emerald-500" />
                                    @error('name') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-gray-700 mb-1">رقم الهاتف <span class="text-red-500">*</span></label>
                                    <input type="tel" wire:model="phone" placeholder="{{ \App\Support\Location\Market::country()->example }}" dir="ltr"
                                        class="w-full text-right px-3.5 py-2 rounded-xl border border-gray-300 text-sm font-mono focus:ring-2 focus:ring-emerald-500" />
                                    @error('phone') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                                </div>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-semibold text-gray-700 mb-1">المحافظة <span class="text-red-500">*</span></label>
                                    <select wire:model="governorate_id"
                                        class="w-full px-3.5 py-2 rounded-xl border border-gray-300 text-sm focus:ring-2 focus:ring-emerald-500 bg-white">
                                        <option value="">اختر المحافظة</option>
                                        @foreach ($governorates as $gov)
                                            <option value="{{ $gov->id }}">{{ $gov->label() }}</option>
                                        @endforeach
                                    </select>
                                    @error('governorate_id') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-gray-700 mb-1">المدينة / الحي <span class="text-red-500">*</span></label>
                                    <input type="text" wire:model="city" placeholder="المعادي / الدقي..."
                                        class="w-full px-3.5 py-2 rounded-xl border border-gray-300 text-sm focus:ring-2 focus:ring-emerald-500" />
                                    @error('city') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                                </div>
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-gray-700 mb-1">العنوان بالتفصيل <span class="text-red-500">*</span></label>
                                <textarea wire:model="address_line" rows="2" placeholder="الشارع، رقم العمارة، رقم الشقة..."
                                    class="w-full px-3.5 py-2 rounded-xl border border-gray-300 text-sm focus:ring-2 focus:ring-emerald-500"></textarea>
                                @error('address_line') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                            </div>

                            <div class="flex items-center gap-2">
                                <input type="checkbox" id="is_default" wire:model="is_default"
                                    class="w-4 h-4 text-emerald-600 rounded border-gray-300 focus:ring-emerald-500" />
                                <label for="is_default" class="text-xs text-gray-700">تعيين كعنوان افتراضي للتوصيل</label>
                            </div>

                            <div class="flex justify-end gap-3 pt-3">
                                <button type="button" wire:click="$set('showAddModal', false)"
                                    class="px-4 py-2 rounded-xl border border-gray-300 text-sm text-gray-600 hover:bg-gray-50">
                                    إلغاء
                                </button>
                                <button type="submit"
                                    class="px-5 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-medium text-sm">
                                    حفظ العنوان
                                </button>
                            </div>
                        </form>
                    </div>
                @endif

                <!-- Saved Addresses Grid -->
                @if ($addresses->isEmpty())
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-12 text-center">
                        <div class="w-16 h-16 mx-auto mb-4 rounded-full bg-emerald-50 flex items-center justify-center text-emerald-600">
                            <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                            </svg>
                        </div>
                        <h3 class="text-base font-semibold text-gray-900 mb-1">لا توجد عناوين محفوظة</h3>
                        <p class="text-sm text-gray-500">أضف عنوانك لتسريع عملية الشراء واستلام الطلبات.</p>
                    </div>
                @else
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        @foreach ($addresses as $address)
                            <div class="bg-white rounded-2xl shadow-sm border {{ $address->is_default ? 'border-emerald-300 bg-emerald-50/20' : 'border-gray-100' }} p-5 flex flex-col justify-between">
                                <div>
                                    <div class="flex items-center justify-between mb-2">
                                        <h3 class="font-bold text-gray-900 text-sm">{{ $address->name }}</h3>
                                        @if ($address->is_default)
                                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800">
                                                افتراضي
                                            </span>
                                        @endif
                                    </div>
                                    <p class="text-xs text-gray-500 font-mono mb-2" dir="ltr" style="text-align: right;">{{ $address->phone }}</p>
                                    <p class="text-xs text-gray-700 leading-relaxed mb-1">{{ $address->address_line }}</p>
                                    <p class="text-xs text-gray-400">{{ $address->city }}، {{ $address->governorate?->name() }}</p>
                                </div>

                                <div class="mt-4 pt-3 border-t border-gray-100 flex items-center justify-between text-xs">
                                    @if (! $address->is_default)
                                        <button type="button" wire:click="setAsDefault({{ $address->id }})" class="text-emerald-600 hover:underline font-medium">
                                            تعيين كافتراضي
                                        </button>
                                    @else
                                        <span></span>
                                    @endif
                                    <button type="button" wire:click="deleteAddress({{ $address->id }})" class="text-red-500 hover:text-red-700">
                                        حذف
                                    </button>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
