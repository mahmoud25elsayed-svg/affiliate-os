<x-filament-panels::page>
    <form wire:submit="save" class="space-y-6">
        {{-- Section 1: نموذج العمولة وهيكل الحساب --}}
        <div class="bg-white dark:bg-gray-900 p-6 rounded-xl border border-gray-200 dark:border-gray-800 shadow-sm space-y-4">
            <div>
                <h3 class="text-base font-bold text-gray-900 dark:text-white">نموذج العمولات المعتمد للمتجر</h3>
                <p class="text-xs text-gray-500">اختر طريقة حساب أرباح المسوقين (سعر حر فوق الجملة أو نسبة مئوية)</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">طريقة حساب العمولة</label>
                    <select wire:model="commission_model" class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm">
                        <option value="markup">نظام السعر الحر فوق الجملة (Markup Only - دروب شيبنج محلي)</option>
                        <option value="percentage">نسبة مئوية من سعر التجزئة (Percentage Only)</option>
                        <option value="both">النظامان معاً (حسب المنتج أو رغبة المسوق)</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">النسبة الافتراضية (%) لنظام النسبة</label>
                    <input type="number" wire:model="default_commission_rate" min="1" max="100" class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm">
                </div>
            </div>
        </div>

        {{-- Section 2: حدود الأسعار والعمولات --}}
        <div class="bg-white dark:bg-gray-900 p-6 rounded-xl border border-gray-200 dark:border-gray-800 shadow-sm space-y-4">
            <div>
                <h3 class="text-base font-bold text-gray-900 dark:text-white">حدود العمولات وهوامش الربح</h3>
                <p class="text-xs text-gray-500">صلاحيات وضوابط للحد الأدنى والأقصى للعمولة لحماية سمعة المتجر والتسعير العادل</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">الحد الأدنى لربح المسوق فوق سعر الجملة ({{ \App\Support\Money::symbol() }})</label>
                    <input type="number" step="0.5" wire:model="min_selling_markup" class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm">
                    @error('min_selling_markup') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">الحد الأقصى لربح المسوق فوق سعر الجملة ({{ \App\Support\Money::symbol() }})</label>
                    <input type="number" step="0.5" wire:model="max_selling_markup" class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm">
                    @error('max_selling_markup') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">الحد الأدنى للعمولة في نظام النسبة ({{ \App\Support\Money::symbol() }})</label>
                    <input type="number" step="0.5" wire:model="min_commission" class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">الحد الأقصى للعمولة في نظام النسبة ({{ \App\Support\Money::symbol() }})</label>
                    <input type="number" step="0.5" wire:model="max_commission" class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm">
                </div>
            </div>
        </div>

        {{-- Section 3: دورة حياة العمولات والسحب --}}
        <div class="bg-white dark:bg-gray-900 p-6 rounded-xl border border-gray-200 dark:border-gray-800 shadow-sm space-y-4">
            <div>
                <h3 class="text-base font-bold text-gray-900 dark:text-white">سياسة الأمان والسحب المالي</h3>
                <p class="text-xs text-gray-500">فترة حماية المرتجعات وقواعد تجميد السحب عند تعديل الحسابات البنكية أو المحافظ</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">فترة حماية المرتجعات (أيام قبل تحرير العمولة)</label>
                    <input type="number" wire:model="return_window_days" min="0" max="90" class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm">
                    <p class="text-[11px] text-gray-400 mt-1">تظل العمولة معلقة حتى انقضاء هذه الفترة بعد تسليم الطلب</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">الحد الأدنى لطلب سحب الأرباح ({{ \App\Support\Money::symbol() }})</label>
                    <input type="number" step="10" wire:model="min_payout" min="10" class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">شرط تحرير العمولة (Release Mode)</label>
                    <select wire:model="release_mode" class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm">
                        <option value="later_of_both">انقضاء فترة المرتجعات وتوريد شركة الشحن (الأكثر أماناً)</option>
                        <option value="return_window">انقضاء فترة المرتجعات فقط بعد التسليم</option>
                        <option value="courier_remittance">بمجرد توريد شركة الشحن لقيمة التحصيل (COD)</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">مدة تجميد السحب عند تعديل بيانات التحويل (ساعات)</label>
                    <input type="number" wire:model="payout_freeze_hours_on_change" min="0" max="168" class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm">
                    <p class="text-[11px] text-gray-400 mt-1">الافتراضي 24 ساعة للحماية من اختراق حسابات المسوقين</p>
                </div>
            </div>
        </div>

        {{-- Section 4: ضوابط الانضمام والصلاحيات --}}
        <div class="bg-white dark:bg-gray-900 p-6 rounded-xl border border-gray-200 dark:border-gray-800 shadow-sm space-y-4">
            <div>
                <h3 class="text-base font-bold text-gray-900 dark:text-white">ضوابط المسوقين والطلبات</h3>
                <p class="text-xs text-gray-500">إدارة تسجيل المسوقين والخصوصية والكوتا اليومية</p>
            </div>

            <div class="space-y-3">
                <label class="flex items-center gap-3 cursor-pointer">
                    <input type="checkbox" wire:model="auto_approve_registrations" class="rounded border-gray-300 text-primary-600 focus:ring-primary-500">
                    <span class="text-sm text-gray-700 dark:text-gray-300 font-medium">تفعيل الموافقة التلقائية على المسوقين الجدد دون مراجعة يدوية</span>
                </label>

                <label class="flex items-center gap-3 cursor-pointer">
                    <input type="checkbox" wire:model="show_customer_phone_to_affiliate" class="rounded border-gray-300 text-primary-600 focus:ring-primary-500">
                    <span class="text-sm text-gray-700 dark:text-gray-300 font-medium">إظهار رقم هاتف العميل النهائي للمسوق داخل تفاصيل الطلبات</span>
                </label>
            </div>

            <div class="pt-2 max-w-sm">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">الحد الأقصى للطلبات اليومية للمسوق (كوتا الحماية من الاحتيال)</label>
                <input type="number" wire:model="default_daily_order_limit" min="1" max="1000" class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm">
            </div>
        </div>

        <div>
            <button type="submit" class="px-6 py-2.5 bg-emerald-600 text-white font-medium rounded-lg hover:bg-emerald-700 focus:ring-4 focus:ring-emerald-200 text-sm">
                حفظ إعدادات برنامج المسوقين
            </button>
        </div>
    </form>
</x-filament-panels::page>

