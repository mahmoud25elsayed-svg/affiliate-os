<x-filament-panels::page>
    <form wire:submit.prevent="save" class="space-y-6">

        <!-- Security & Webhook Info Banner -->
        <div class="bg-primary-50 dark:bg-primary-950/40 border border-primary-200 dark:border-primary-800 rounded-xl p-5 shadow-sm">
            <div class="flex items-start gap-4">
                <div class="p-2 bg-primary-100 dark:bg-primary-900 rounded-lg text-primary-600 dark:text-primary-300">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                    </svg>
                </div>
                <div>
                    <h3 class="text-sm font-bold text-primary-900 dark:text-primary-100">
                        {{ __('حماية وتشفير البيانات المالية (Tenant Encrypted Credentials)') }}
                    </h3>
                    <p class="text-xs text-primary-800 dark:text-primary-300 mt-1 leading-relaxed">
                        {{ __('يتم تشفير كافة المفاتيح السرية (API Keys & HMAC Secrets) تلقائياً بتقنية') }} <strong>AES-256-GCM</strong> {{ __('على مستوى قاعدة البيانات، مع عزل كامل لكل متجر.') }}
                    </p>
                    <div class="mt-3 flex items-center gap-2 text-xs font-mono bg-white dark:bg-gray-900 px-3 py-1.5 rounded border border-primary-300 dark:border-primary-700 text-gray-800 dark:text-gray-200">
                        <span class="text-gray-400">{{ __('رابط الويب هوك الخاص بك (Webhook Callback URL):') }}</span>
                        <span class="font-bold text-primary-600 dark:text-primary-400 select-all">{{ url('/api/webhooks/payments/paymob') }}</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Paymob Gateway Configuration -->
        <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl p-6 shadow-sm space-y-6">
            <div class="flex items-center justify-between border-b border-gray-100 dark:border-gray-800 pb-4">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-lg bg-blue-600 flex items-center justify-center text-white font-bold text-lg shadow-sm">
                        P
                    </div>
                    <div>
                        <h2 class="text-base font-bold text-gray-900 dark:text-white">
                            {{ __('بوابة باي موب (Paymob)') }}
                        </h2>
                        <p class="text-xs text-gray-500">
                            {{ __('بطاقات فيزا وماستركارد، كروت ميزة، محافظ الهاتف (فودافون كاش، إي آند كاش، وي باي، أورنج كاش)، إنستاباي.') }}
                        </p>
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" wire:model="paymob_enabled" class="sr-only peer">
                        <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer dark:bg-gray-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-blue-600"></div>
                        <span class="mr-3 text-sm font-semibold text-gray-700 dark:text-gray-300">
                            {{ $paymob_enabled ? __('مفعل') : __('معطل') }}
                        </span>
                    </label>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Environment Mode -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1.5">
                        {{ __('بيئة التشغيل (Environment) *') }}
                    </label>
                    <select wire:model="paymob_mode" class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white shadow-sm text-sm">
                        <option value="sandbox">{{ __('بيئة تجريبية (Sandbox / Test Mode)') }}</option>
                        <option value="live">{{ __('بيئة حية للإنتاج (Live / Production)') }}</option>
                    </select>
                </div>

                <!-- Integration ID -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1.5">
                        {{ __('معرّف الدمج (Integration ID)') }}
                    </label>
                    <input type="text" wire:model="paymob_integration_id" placeholder="{{ __('مثال: 123456') }}" class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white shadow-sm text-sm">
                </div>

                <!-- Iframe ID -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1.5">
                        {{ __('معرّف الإطار (Iframe ID)') }}
                    </label>
                    <input type="text" wire:model="paymob_iframe_id" placeholder="{{ __('مثال: 789012') }}" class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white shadow-sm text-sm">
                </div>

                <!-- API Secret Key -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1.5">
                        {{ __('المفتاح السري (API Secret Key)') }}
                    </label>
                    <input type="password" wire:model="paymob_api_key" placeholder="ZXhjYXB0X..." class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white shadow-sm text-sm">
                </div>

                <!-- HMAC Secret Key -->
                <div class="md:col-span-2">
                    <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1.5">
                        {{ __('مفتاح توثيق الإشعارات (HMAC Secret Key) *') }}
                    </label>
                    <input type="password" wire:model="paymob_hmac_secret" placeholder="{{ __('يستخدم للتحقق المشفر من صحة إشعارات الويب هوك') }}" class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white shadow-sm text-sm">
                    <p class="text-xs text-gray-400 mt-1">
                        {{ __('يمكنك العثور على HMAC Secret في لوحة تحكم Paymob تحت Settings > Payment Integrations.') }}
                    </p>
                </div>
            </div>
        </div>

        <!-- Fake Gateway (Development & Automated Testing) -->
        <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl p-6 shadow-sm space-y-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-lg bg-amber-500 flex items-center justify-center text-white font-bold text-lg shadow-sm">
                        🧪
                    </div>
                    <div>
                        <h2 class="text-base font-bold text-gray-900 dark:text-white">
                            {{ __('البوابة التجريبية السريعة (Fake Gateway Simulator)') }}
                        </h2>
                        <p class="text-xs text-gray-500">
                            {{ __('مخصصة لاختبار تدفقات الطلبات وتكامل السلة دون الحاجة لبيانات بنكية حقيقية.') }}
                        </p>
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" wire:model="fake_enabled" class="sr-only peer">
                        <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer dark:bg-gray-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-amber-500"></div>
                        <span class="mr-3 text-sm font-semibold text-gray-700 dark:text-gray-300">
                            {{ $fake_enabled ? __('مفعل') : __('معطل') }}
                        </span>
                    </label>
                </div>
            </div>
        </div>

        <!-- Fawry Gateway (P13 Extension Ready) -->
        <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl p-6 shadow-sm space-y-6">
            <div class="flex items-center justify-between border-b border-gray-100 dark:border-gray-800 pb-4">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-lg bg-yellow-500 flex items-center justify-center text-white font-bold text-lg shadow-sm">
                        F
                    </div>
                    <div>
                        <h2 class="text-base font-bold text-gray-900 dark:text-white">
                            {{ __('بوابة فوري (Fawry Pay)') }}
                        </h2>
                        <p class="text-xs text-gray-500">
                            {{ __('الدفع عبر كود فوري في المنافذ والمحلات أو البطاقات المصرفية.') }}
                        </p>
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" wire:model="fawry_enabled" class="sr-only peer">
                        <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer dark:bg-gray-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-yellow-500"></div>
                        <span class="mr-3 text-sm font-semibold text-gray-700 dark:text-gray-300">
                            {{ $fawry_enabled ? __('مفعل') : __('معطل') }}
                        </span>
                    </label>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1.5">
                        {{ __('كود التاجر (Merchant Code)') }}
                    </label>
                    <input type="text" wire:model="fawry_merchant_code" class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white shadow-sm text-sm">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1.5">
                        {{ __('مفتاح الأمان (Security Key)') }}
                    </label>
                    <input type="password" wire:model="fawry_security_key" class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white shadow-sm text-sm">
                </div>
            </div>
        </div>

        <!-- Kashier Gateway (P13 Extension Ready) -->
        <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl p-6 shadow-sm space-y-6">
            <div class="flex items-center justify-between border-b border-gray-100 dark:border-gray-800 pb-4">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-lg bg-emerald-600 flex items-center justify-center text-white font-bold text-lg shadow-sm">
                        K
                    </div>
                    <div>
                        <h2 class="text-base font-bold text-gray-900 dark:text-white">
                            {{ __('بوابة كاشير (Kashier)') }}
                        </h2>
                        <p class="text-xs text-gray-500">
                            {{ __('المدفوعات الإلكترونية وتقسيط البنوك المصرية.') }}
                        </p>
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" wire:model="kashier_enabled" class="sr-only peer">
                        <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer dark:bg-gray-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-emerald-600"></div>
                        <span class="mr-3 text-sm font-semibold text-gray-700 dark:text-gray-300">
                            {{ $kashier_enabled ? __('مفعل') : __('معطل') }}
                        </span>
                    </label>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1.5">
                        {{ __('معرّف التاجر (Merchant ID)') }}
                    </label>
                    <input type="text" wire:model="kashier_merchant_id" class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white shadow-sm text-sm">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1.5">
                        {{ __('مفتاح الـ API (API Secret Key)') }}
                    </label>
                    <input type="password" wire:model="kashier_api_key" class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white shadow-sm text-sm">
                </div>
            </div>
        </div>

        <!-- Submit Button -->
        <div class="flex justify-end">
            <x-filament::button type="submit" size="lg">
                {{ __('حفظ وتشفير إعدادات بوابات الدفع') }}
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
