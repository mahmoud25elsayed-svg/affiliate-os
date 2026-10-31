<x-filament-panels::page>
    <form wire:submit="save" class="space-y-6">
        {{-- Section 1: نموذج العمولة وهيكل الحساب --}}
        <div class="bg-white dark:bg-gray-900 p-6 rounded-xl border border-gray-200 dark:border-gray-800 shadow-sm space-y-4">
            <div>
                <h3 class="text-base font-bold text-gray-900 dark:text-white">{{ __('نموذج العمولات المعتمد للمتجر') }}</h3>
                <p class="text-xs text-gray-500">{{ __('اختر طريقة حساب أرباح المسوقين (سعر حر فوق الجملة أو نسبة مئوية)') }}</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('طريقة حساب العمولة') }}</label>
                    <select wire:model="commission_model" class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm">
                        <option value="markup">{{ __('نظام السعر الحر فوق الجملة (Markup Only - دروب شيبنج محلي)') }}</option>
                        <option value="percentage">{{ __('نسبة مئوية من سعر التجزئة (Percentage Only)') }}</option>
                        <option value="both">{{ __('النظامان معاً (حسب المنتج أو رغبة المسوق)') }}</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('النسبة الافتراضية (%) لنظام النسبة') }}</label>
                    <input type="number" wire:model="default_commission_rate" min="1" max="100" class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm">
                </div>
            </div>
        </div>

        {{-- Section 2: حدود الأسعار والعمولات --}}
        <div class="bg-white dark:bg-gray-900 p-6 rounded-xl border border-gray-200 dark:border-gray-800 shadow-sm space-y-4">
            <div>
                <h3 class="text-base font-bold text-gray-900 dark:text-white">{{ __('حدود العمولات وهوامش الربح') }}</h3>
                <p class="text-xs text-gray-500">{{ __('صلاحيات وضوابط للحد الأدنى والأقصى للعمولة لحماية سمعة المتجر والتسعير العادل') }}</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('الحد الأدنى لربح المسوق فوق سعر الجملة (') }}{{ \App\Support\Money::symbol() }})</label>
                    <input type="number" step="0.5" wire:model="min_selling_markup" class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm">
                    @error('min_selling_markup') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('الحد الأقصى لربح المسوق فوق سعر الجملة (') }}{{ \App\Support\Money::symbol() }})</label>
                    <input type="number" step="0.5" wire:model="max_selling_markup" class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm">
                    @error('max_selling_markup') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('الحد الأدنى للعمولة في نظام النسبة (') }}{{ \App\Support\Money::symbol() }})</label>
                    <input type="number" step="0.5" wire:model="min_commission" class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('الحد الأقصى للعمولة في نظام النسبة (') }}{{ \App\Support\Money::symbol() }})</label>
                    <input type="number" step="0.5" wire:model="max_commission" class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm">
                </div>
            </div>
        </div>

        {{-- Section 3: دورة حياة العمولات والسحب --}}
        <div class="bg-white dark:bg-gray-900 p-6 rounded-xl border border-gray-200 dark:border-gray-800 shadow-sm space-y-4">
            <div>
                <h3 class="text-base font-bold text-gray-900 dark:text-white">{{ __('سياسة الأمان والسحب المالي') }}</h3>
                <p class="text-xs text-gray-500">{{ __('فترة حماية المرتجعات وقواعد تجميد السحب عند تعديل الحسابات البنكية أو المحافظ') }}</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('فترة حماية المرتجعات (أيام قبل تحرير العمولة)') }}</label>
                    <input type="number" wire:model="return_window_days" min="0" max="90" class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm">
                    <p class="text-[11px] text-gray-400 mt-1">{{ __('تظل العمولة معلقة حتى انقضاء هذه الفترة بعد تسليم الطلب') }}</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('الحد الأدنى لطلب سحب الأرباح (') }}{{ \App\Support\Money::symbol() }})</label>
                    <input type="number" step="10" wire:model="min_payout" min="10" class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('شرط تحرير العمولة (Release Mode)') }}</label>
                    <select wire:model="release_mode" class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm">
                        <option value="later_of_both">{{ __('انقضاء فترة المرتجعات وتوريد شركة الشحن (الأكثر أماناً)') }}</option>
                        <option value="return_window">{{ __('انقضاء فترة المرتجعات فقط بعد التسليم') }}</option>
                        <option value="courier_remittance">{{ __('بمجرد توريد شركة الشحن لقيمة التحصيل (COD)') }}</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('مدة تجميد السحب عند تعديل بيانات التحويل (ساعات)') }}</label>
                    <input type="number" wire:model="payout_freeze_hours_on_change" min="0" max="168" class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm">
                    <p class="text-[11px] text-gray-400 mt-1">{{ __('الافتراضي 24 ساعة للحماية من اختراق حسابات المسوقين') }}</p>
                </div>
            </div>
        </div>

        {{-- Section 4: ضوابط الانضمام والصلاحيات --}}
        <div class="bg-white dark:bg-gray-900 p-6 rounded-xl border border-gray-200 dark:border-gray-800 shadow-sm space-y-4">
            <div>
                <h3 class="text-base font-bold text-gray-900 dark:text-white">{{ __('ضوابط المسوقين والطلبات') }}</h3>
                <p class="text-xs text-gray-500">{{ __('إدارة تسجيل المسوقين والخصوصية والكوتا اليومية') }}</p>
            </div>

            <div class="space-y-3">
                <label class="flex items-center gap-3 cursor-pointer">
                    <input type="checkbox" wire:model="auto_approve_registrations" class="rounded border-gray-300 text-primary-600 focus:ring-primary-500">
                    <span class="text-sm text-gray-700 dark:text-gray-300 font-medium">{{ __('تفعيل الموافقة التلقائية على المسوقين الجدد دون مراجعة يدوية') }}</span>
                </label>

                <label class="flex items-center gap-3 cursor-pointer">
                    <input type="checkbox" wire:model="show_customer_phone_to_affiliate" class="rounded border-gray-300 text-primary-600 focus:ring-primary-500">
                    <span class="text-sm text-gray-700 dark:text-gray-300 font-medium">{{ __('إظهار رقم هاتف العميل النهائي للمسوق داخل تفاصيل الطلبات') }}</span>
                </label>
            </div>

            <div class="pt-2 max-w-sm">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ __('الحد الأقصى للطلبات اليومية للمسوق (كوتا الحماية من الاحتيال)') }}</label>
                <input type="number" wire:model="default_daily_order_limit" min="1" max="1000" class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm">
            </div>
        </div>

        <div>
            <button type="submit" class="px-6 py-2.5 bg-emerald-600 text-white font-medium rounded-lg hover:bg-emerald-700 focus:ring-4 focus:ring-emerald-200 text-sm">
                {{ __('حفظ إعدادات برنامج المسوقين') }}
            </button>
        </div>
    </form>
</x-filament-panels::page>

