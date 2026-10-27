<x-filament-panels::page>
    <form wire:submit="save" class="space-y-6">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 bg-white dark:bg-gray-900 p-6 rounded-xl border border-gray-200 dark:border-gray-800 shadow-sm">
            <div class="col-span-full">
                <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-1">الهوية الأساسية للمتجر</h3>
                <p class="text-sm text-gray-500 dark:text-gray-400">تخصيص اسم وشعار وألوان متجرك الإلكتروني</p>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">اسم المتجر</label>
                <input type="text" wire:model="platform_name" class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm focus:ring-primary-500 focus:border-primary-500" required>
                @error('platform_name') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">رابط الشعار (Logo URL)</label>
                <input type="text" wire:model="logo_url" placeholder="https://example.com/logo.png" class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm focus:ring-primary-500 focus:border-primary-500">
                @error('logo_url') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">اللون الأساسي</label>
                <div class="flex items-center gap-3">
                    <input type="color" wire:model="primary_color" class="h-10 w-16 rounded border border-gray-300 cursor-pointer">
                    <input type="text" wire:model="primary_color" class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm">
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">اللون الثانوي</label>
                <div class="flex items-center gap-3">
                    <input type="color" wire:model="secondary_color" class="h-10 w-16 rounded border border-gray-300 cursor-pointer">
                    <input type="text" wire:model="secondary_color" class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm">
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">اللغة الافتراضية</label>
                <select wire:model="default_locale" class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm">
                    <option value="ar">العربية (Arabic)</option>
                    <option value="en">الإنجليزية (English)</option>
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">العملة الافتراضية</label>
                <input type="text" wire:model="default_currency" class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm" readonly>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 bg-white dark:bg-gray-900 p-6 rounded-xl border border-gray-200 dark:border-gray-800 shadow-sm">
            <div class="col-span-full">
                <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-1">معلومات التواصل وخدمة العملاء</h3>
                <p class="text-sm text-gray-500 dark:text-gray-400">تظهر للعملاء في واجهة المتجر والفواتير</p>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">رقم الهاتف</label>
                <input type="text" wire:model="support_phone" class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">واتساب (WhatsApp)</label>
                <input type="text" wire:model="support_whatsapp" class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">البريد الإلكتروني</label>
                <input type="email" wire:model="support_email" class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm">
            </div>
        </div>

        <div class="flex justify-end">
            <button type="submit" class="px-6 py-2.5 bg-primary-600 hover:bg-primary-500 text-white font-bold rounded-lg shadow transition">
                حفظ الإعدادات
            </button>
        </div>
    </form>
</x-filament-panels::page>
