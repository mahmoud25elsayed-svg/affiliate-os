<div class="py-12 bg-gray-50 min-h-[70vh]">
    <div class="max-w-md mx-auto px-4 sm:px-6">
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 sm:p-8">
            <div class="text-center mb-8">
                <h1 class="text-2xl font-bold text-gray-900 mb-2">{{ __('إنشاء حساب جديد') }}</h1>
                <p class="text-sm text-gray-500">{{ __('سجل بياناتك للتمتع بتجربة تسوق سريعة ومتابعة طلباتك') }}</p>
            </div>

            @if ($errorMessage)
                <div class="mb-4 p-3.5 bg-red-50 border border-red-200 rounded-xl text-sm text-red-700 flex items-center gap-2">
                    <svg class="w-5 h-5 flex-shrink-0 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span>{{ $errorMessage }}</span>
                </div>
            @endif

            <form wire:submit.prevent="register" class="space-y-4">
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('الاسم الأول') }} <span class="text-red-500">*</span></label>
                        <input type="text" wire:model="first_name" placeholder="{{ __('محمد') }}"
                            class="w-full px-4 py-2.5 rounded-xl border border-gray-300 focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 text-sm" />
                        @error('first_name') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('اسم العائلة') }}</label>
                        <input type="text" wire:model="last_name" placeholder="{{ __('أحمد') }}"
                            class="w-full px-4 py-2.5 rounded-xl border border-gray-300 focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 text-sm" />
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('رقم الهاتف المحمول') }} <span class="text-red-500">*</span></label>
                    <input type="tel" wire:model="phone" placeholder="{{ \App\Support\Location\Market::country()->example }}" dir="ltr"
                        class="w-full text-right px-4 py-2.5 rounded-xl border border-gray-300 focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 text-sm font-mono" />
                    @error('phone') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('البريد الإلكتروني') }} <span class="text-red-500">*</span></label>
                    <input type="email" wire:model="email" placeholder="example@domain.com" dir="ltr"
                        class="w-full text-left px-4 py-2.5 rounded-xl border border-gray-300 focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 text-sm" />
                    @error('email') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('كلمة المرور') }} <span class="text-red-500">*</span></label>
                    <input type="password" wire:model="password" placeholder="{{ __('8 أحرف على الأقل') }}"
                        class="w-full px-4 py-2.5 rounded-xl border border-gray-300 focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 text-sm" />
                    @error('password') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('تأكيد كلمة المرور') }} <span class="text-red-500">*</span></label>
                    <input type="password" wire:model="password_confirmation" placeholder="{{ __('أعد إدخال كلمة المرور') }}"
                        class="w-full px-4 py-2.5 rounded-xl border border-gray-300 focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 text-sm" />
                </div>

                <button type="submit" wire:loading.attr="disabled"
                    class="w-full py-3 px-4 bg-emerald-600 hover:bg-emerald-700 text-white font-medium rounded-xl text-sm shadow-sm transition-colors flex items-center justify-center gap-2 mt-6">
                    <span wire:loading.remove wire:target="register">{{ __('إنشاء الحساب') }}</span>
                    <span wire:loading wire:target="register" class="inline-block animate-spin rounded-full h-4 w-4 border-2 border-white border-t-transparent"></span>
                </button>
            </form>

            <div class="mt-8 pt-6 border-t border-gray-100 text-center">
                <p class="text-sm text-gray-600">
                    {{ __('لديك حساب بالفعل؟') }}
                    <a href="{{ route('storefront.login') }}" class="font-medium text-emerald-600 hover:text-emerald-700 hover:underline">
                        {{ __('تسجيل الدخول') }}
                    </a>
                </p>
            </div>
        </div>
    </div>
</div>
