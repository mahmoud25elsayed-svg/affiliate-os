<div class="py-12 bg-gray-50 min-h-[70vh]">
    <div class="max-w-md mx-auto px-4 sm:px-6">
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 sm:p-8">
            <div class="text-center mb-8">
                <h1 class="text-2xl font-bold text-gray-900 mb-2">تسجيل الدخول</h1>
                <p class="text-sm text-gray-500">سجل الدخول لمتابعة طلباتك وإدارة عناوينك ومفضلتك</p>
            </div>

            <!-- Tabs -->
            <div class="flex rounded-xl bg-gray-100 p-1 mb-6">
                <button type="button" wire:click="setMode('otp')"
                    class="flex-1 py-2 text-sm font-semibold rounded-lg transition-all {{ $mode === 'otp' ? 'bg-white text-emerald-700 shadow-sm' : 'text-gray-500 hover:text-gray-800' }}">
                    الهاتف (رمز التحقق)
                </button>
                <button type="button" wire:click="setMode('password')"
                    class="flex-1 py-2 text-sm font-semibold rounded-lg transition-all {{ $mode === 'password' ? 'bg-white text-emerald-700 shadow-sm' : 'text-gray-500 hover:text-gray-800' }}">
                    البريد وكلمة المرور
                </button>
            </div>

            @if ($errorMessage)
                <div class="mb-4 p-3.5 bg-red-50 border border-red-200 rounded-xl text-sm text-red-700 flex items-center gap-2">
                    <svg class="w-5 h-5 flex-shrink-0 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span>{{ $errorMessage }}</span>
                </div>
            @endif

            @if ($successMessage)
                <div class="mb-4 p-3.5 bg-emerald-50 border border-emerald-200 rounded-xl text-sm text-emerald-800 flex items-center gap-2">
                    <svg class="w-5 h-5 flex-shrink-0 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                    <span>{{ $successMessage }}</span>
                </div>
            @endif

            <!-- OTP Form -->
            @if ($mode === 'otp')
                @if (! $otpSent)
                    <form wire:submit.prevent="requestOtp" class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">رقم الهاتف المحمول</label>
                            <div class="relative">
                                <input type="tel" wire:model="phone" placeholder="01012345678" dir="ltr"
                                    class="w-full text-right px-4 py-2.5 rounded-xl border border-gray-300 focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 text-sm font-mono" />
                            </div>
                            <p class="text-xs text-gray-400 mt-1">يجب أن يكون رقم محمول مصري (010، 011، 012، 015)</p>
                        </div>

                        <button type="submit" wire:loading.attr="disabled"
                            class="w-full py-3 px-4 bg-emerald-600 hover:bg-emerald-700 text-white font-medium rounded-xl text-sm shadow-sm transition-colors flex items-center justify-center gap-2">
                            <span wire:loading.remove wire:target="requestOtp">إرسال رمز التحقق</span>
                            <span wire:loading wire:target="requestOtp" class="inline-block animate-spin rounded-full h-4 w-4 border-2 border-white border-t-transparent"></span>
                        </button>
                    </form>
                @else
                    <form wire:submit.prevent="verifyOtp" class="space-y-4">
                        <div>
                            <div class="flex justify-between items-center mb-1">
                                <label class="block text-sm font-medium text-gray-700">رمز التحقق (OTP)</label>
                                <button type="button" wire:click="$set('otpSent', false)" class="text-xs text-emerald-600 hover:underline">
                                    تغيير الرقم
                                </button>
                            </div>
                            <input type="text" wire:model="otp" placeholder="123456" maxlength="6" dir="ltr"
                                class="w-full text-center tracking-widest text-xl px-4 py-3 rounded-xl border border-gray-300 focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 font-mono font-bold" />
                            <p class="text-xs text-gray-400 mt-1 text-center">أدخل الرمز المكون من 6 أرقام المرسل إلى {{ $phone }}</p>
                        </div>

                        <button type="submit" wire:loading.attr="disabled"
                            class="w-full py-3 px-4 bg-emerald-600 hover:bg-emerald-700 text-white font-medium rounded-xl text-sm shadow-sm transition-colors flex items-center justify-center gap-2">
                            <span wire:loading.remove wire:target="verifyOtp">تأكيد الدخول</span>
                            <span wire:loading wire:target="verifyOtp" class="inline-block animate-spin rounded-full h-4 w-4 border-2 border-white border-t-transparent"></span>
                        </button>
                    </form>
                @endif
            @else
                <!-- Email & Password Form -->
                <form wire:submit.prevent="loginWithPassword" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">البريد الإلكتروني</label>
                        <input type="email" wire:model="email" placeholder="example@domain.com" dir="ltr"
                            class="w-full text-left px-4 py-2.5 rounded-xl border border-gray-300 focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 text-sm" />
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">كلمة المرور</label>
                        <input type="password" wire:model="password"
                            class="w-full px-4 py-2.5 rounded-xl border border-gray-300 focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 text-sm" />
                    </div>

                    <button type="submit" wire:loading.attr="disabled"
                        class="w-full py-3 px-4 bg-emerald-600 hover:bg-emerald-700 text-white font-medium rounded-xl text-sm shadow-sm transition-colors flex items-center justify-center gap-2">
                        <span wire:loading.remove wire:target="loginWithPassword">تسجيل الدخول</span>
                        <span wire:loading wire:target="loginWithPassword" class="inline-block animate-spin rounded-full h-4 w-4 border-2 border-white border-t-transparent"></span>
                    </button>
                </form>
            @endif

            <div class="mt-8 pt-6 border-t border-gray-100 text-center">
                <p class="text-sm text-gray-600">
                    ليس لديك حساب؟
                    <a href="{{ route('storefront.register') }}" class="font-medium text-emerald-600 hover:text-emerald-700 hover:underline">
                        إنشاء حساب جديد
                    </a>
                </p>
            </div>
        </div>
    </div>
</div>
