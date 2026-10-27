<div class="py-10 bg-gray-50 min-h-[75vh]">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="mb-8">
            <h1 class="text-2xl font-bold text-gray-900">حسابي</h1>
            <p class="text-sm text-gray-500 mt-1">إدارة بيانات الحساب والخصوصية</p>
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
                        class="flex items-center gap-3 px-4 py-2.5 rounded-xl text-sm font-medium text-gray-600 hover:bg-gray-50 hover:text-gray-900 transition-colors">
                        <svg class="w-5 h-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
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
                        class="flex items-center gap-3 px-4 py-2.5 rounded-xl text-sm font-semibold bg-emerald-50 text-emerald-800">
                        <svg class="w-5 h-5 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                        </svg>
                        <span>الملف الشخصي</span>
                    </a>
                </nav>
            </div>

            <!-- Profile Forms -->
            <div class="md:col-span-3 space-y-6">
                <!-- Personal Info -->
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                    <h2 class="text-base font-bold text-gray-900 mb-4 pb-2 border-b border-gray-100">البيانات الشخصية</h2>

                    @if ($successMessage)
                        <div class="mb-4 p-3 bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm rounded-xl">
                            {{ $successMessage }}
                        </div>
                    @endif

                    <form wire:submit.prevent="updateProfile" class="space-y-4 max-w-xl">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-semibold text-gray-700 mb-1">الاسم الأول <span class="text-red-500">*</span></label>
                                <input type="text" wire:model="first_name"
                                    class="w-full px-3.5 py-2 rounded-xl border border-gray-300 text-sm focus:ring-2 focus:ring-emerald-500" />
                                @error('first_name') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-700 mb-1">اسم العائلة</label>
                                <input type="text" wire:model="last_name"
                                    class="w-full px-3.5 py-2 rounded-xl border border-gray-300 text-sm focus:ring-2 focus:ring-emerald-500" />
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-gray-700 mb-1">البريد الإلكتروني</label>
                            <input type="email" wire:model="email" dir="ltr"
                                class="w-full text-left px-3.5 py-2 rounded-xl border border-gray-300 text-sm focus:ring-2 focus:ring-emerald-500" />
                            @error('email') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <button type="submit"
                                class="px-5 py-2 bg-emerald-600 hover:bg-emerald-700 text-white font-medium text-sm rounded-xl transition-colors">
                                حفظ التغييرات
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Password Change -->
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                    <h2 class="text-base font-bold text-gray-900 mb-4 pb-2 border-b border-gray-100">تغيير كلمة المرور</h2>

                    @if ($passwordSuccessMessage)
                        <div class="mb-4 p-3 bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm rounded-xl">
                            {{ $passwordSuccessMessage }}
                        </div>
                    @endif

                    <form wire:submit.prevent="updatePassword" class="space-y-4 max-w-xl">
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 mb-1">كلمة المرور الحالية <span class="text-red-500">*</span></label>
                            <input type="password" wire:model="current_password"
                                class="w-full px-3.5 py-2 rounded-xl border border-gray-300 text-sm focus:ring-2 focus:ring-emerald-500" />
                            @error('current_password') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-semibold text-gray-700 mb-1">كلمة المرور الجديدة <span class="text-red-500">*</span></label>
                                <input type="password" wire:model="new_password"
                                    class="w-full px-3.5 py-2 rounded-xl border border-gray-300 text-sm focus:ring-2 focus:ring-emerald-500" />
                                @error('new_password') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-700 mb-1">تأكيد كلمة المرور <span class="text-red-500">*</span></label>
                                <input type="password" wire:model="new_password_confirmation"
                                    class="w-full px-3.5 py-2 rounded-xl border border-gray-300 text-sm focus:ring-2 focus:ring-emerald-500" />
                            </div>
                        </div>

                        <div>
                            <button type="submit"
                                class="px-5 py-2 bg-gray-900 hover:bg-black text-white font-medium text-sm rounded-xl transition-colors">
                                تحديث كلمة المرور
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Delete Account (GDPR) -->
                <div class="bg-red-50/50 rounded-2xl border border-red-200 p-6">
                    <h2 class="text-base font-bold text-red-900 mb-2">حذف الحساب والخصوصية</h2>
                    <p class="text-xs text-red-700 leading-relaxed mb-4">
                        عند حذف حسابك، سيتم إخفاء وتجريد كافة بياناتك الشخصية (الاسم، الهاتف، البريد، العناوين) وفقاً لحق النسيان وحماية البيانات. ستبقى السجلات المالية للطلبات السابقة محفوظة للأغراض المحاسبية فقط دون أي ربط بشخصك.
                    </p>

                    @if ($errorMessage)
                        <div class="mb-4 p-3 bg-red-100 border border-red-200 text-red-800 text-xs rounded-xl">
                            {{ $errorMessage }}
                        </div>
                    @endif

                    <form wire:submit.prevent="deleteAccount" class="space-y-4">
                        <div class="flex items-center gap-2">
                            <input type="checkbox" id="confirm_del" wire:model="confirmDelete"
                                class="w-4 h-4 text-red-600 rounded border-red-300 focus:ring-red-500" />
                            <label for="confirm_del" class="text-xs font-medium text-red-800">
                                أقر برغبتي في حذف حسابي نهائياً وإخفاء بياناتي الشخصية
                            </label>
                        </div>

                        <button type="submit"
                            onclick="return confirm('هل أنت متأكد تماماً من حذف حسابك؟ لا يمكن التراجع عن هذا الإجراء.')"
                            class="px-5 py-2 bg-red-600 hover:bg-red-700 text-white font-medium text-sm rounded-xl transition-colors shadow-sm">
                            حذف الحساب نهائياً
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
