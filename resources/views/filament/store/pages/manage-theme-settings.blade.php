<x-filament-panels::page>
    <form wire:submit="save" class="space-y-6">
        <!-- 1. الألوان والخطوط -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 bg-white dark:bg-gray-900 p-6 rounded-xl border border-gray-200 dark:border-gray-800 shadow-sm">
            <div class="col-span-full">
                <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-1">{{ __('الألوان والخطوط (Google Fonts)') }}</h3>
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('تخصيص الهوية البصرية ونوع الخط لواجهة المتجر') }}</p>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">{{ __('نوع الخط العربي') }}</label>
                <select wire:model="font_family" class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm">
                    <option value="Cairo">{{ __('Cairo (الافتراضي - عصري وشائع)') }}</option>
                    <option value="Tajawal">{{ __('Tajawal (أنيق ومقروء)') }}</option>
                    <option value="Readex Pro">{{ __('Readex Pro (حديث ودقيق)') }}</option>
                    <option value="Almarai">{{ __('Almarai (مبسط ومريح للعين)') }}</option>
                    <option value="Alexandria">{{ __('Alexandria (هندسي مميز)') }}</option>
                    <option value="IBM Plex Sans Arabic">{{ __('IBM Plex Sans Arabic (تقني ورسمي)') }}</option>
                </select>
                @error('font_family') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">{{ __('اللون الأساسي (Primary)') }}</label>
                <div class="flex items-center gap-3">
                    <input type="color" wire:model="primary_color" class="h-10 w-16 rounded border border-gray-300 cursor-pointer">
                    <input type="text" wire:model="primary_color" class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm">
                </div>
                @error('primary_color') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">{{ __('اللون الثانوي (Secondary)') }}</label>
                <div class="flex items-center gap-3">
                    <input type="color" wire:model="secondary_color" class="h-10 w-16 rounded border border-gray-300 cursor-pointer">
                    <input type="text" wire:model="secondary_color" class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm">
                </div>
                @error('secondary_color') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">{{ __('لون التمييز (Accent)') }}</label>
                <div class="flex items-center gap-3">
                    <input type="color" wire:model="accent_color" class="h-10 w-16 rounded border border-gray-300 cursor-pointer">
                    <input type="text" wire:model="accent_color" class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm">
                </div>
                @error('accent_color') <span class="text-xs text-red-500">{{ $message }}</span> @enderror
            </div>

            <div class="col-span-2">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">{{ __('رابط الشعار (Logo URL)') }}</label>
                <input type="text" wire:model="logo_url" placeholder="https://example.com/logo.png" class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm">
            </div>

            <div class="col-span-2">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">{{ __('رابط أيقونة المتصفح (Favicon URL)') }}</label>
                <input type="text" wire:model="favicon_url" placeholder="https://example.com/favicon.ico" class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm">
            </div>
        </div>

        <!-- 2. بانر الصفحة الرئيسية (Hero Banner) -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 bg-white dark:bg-gray-900 p-6 rounded-xl border border-gray-200 dark:border-gray-800 shadow-sm">
            <div class="col-span-full">
                <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-1">{{ __('بانر الصفحة الرئيسية (Hero Banner)') }}</h3>
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('إعدادات الواجهة الترحيبية الأولى التي يشاهدها الزائر') }}</p>
            </div>

            <div class="col-span-full">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">{{ __('العنوان الرئيسي للبانر') }}</label>
                <input type="text" wire:model="hero_headline" class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm" required>
            </div>

            <div class="col-span-full">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">{{ __('العنوان الفرعي / الوصف') }}</label>
                <textarea wire:model="hero_subheadline" rows="2" class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm" required></textarea>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">{{ __('نص زر الدعوة (Call To Action)') }}</label>
                <input type="text" wire:model="hero_button_text" class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm" required>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">{{ __('رابط زر الدعوة (URL)') }}</label>
                <input type="text" wire:model="hero_button_url" class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm" required>
            </div>

            <div class="col-span-full">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">{{ __('رابط صورة البانر (اختياري)') }}</label>
                <input type="text" wire:model="hero_image_url" placeholder="https://example.com/banner.jpg" class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm">
            </div>
        </div>

        <!-- 3. أقسام الصفحة الرئيسية -->
        <div class="bg-white dark:bg-gray-900 p-6 rounded-xl border border-gray-200 dark:border-gray-800 shadow-sm space-y-4">
            <div>
                <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-1">{{ __('أقسام الصفحة الرئيسية') }}</h3>
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('تفعيل أو تعطيل أقسام الصفحة الرئيسية') }}</p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4">
                <label class="flex items-center gap-3 p-3 rounded-lg border border-gray-200 dark:border-gray-700 cursor-pointer">
                    <input type="checkbox" wire:model="show_hero_banner" class="rounded text-primary-600 focus:ring-primary-500">
                    <span class="text-sm font-medium text-gray-800 dark:text-gray-200">{{ __('بانر الترحيب (Hero)') }}</span>
                </label>

                <label class="flex items-center gap-3 p-3 rounded-lg border border-gray-200 dark:border-gray-700 cursor-pointer">
                    <input type="checkbox" wire:model="show_featured_categories" class="rounded text-primary-600 focus:ring-primary-500">
                    <span class="text-sm font-medium text-gray-800 dark:text-gray-200">{{ __('الفئات المميزة') }}</span>
                </label>

                <label class="flex items-center gap-3 p-3 rounded-lg border border-gray-200 dark:border-gray-700 cursor-pointer">
                    <input type="checkbox" wire:model="show_featured_products" class="rounded text-primary-600 focus:ring-primary-500">
                    <span class="text-sm font-medium text-gray-800 dark:text-gray-200">{{ __('المنتجات المميزة') }}</span>
                </label>

                <label class="flex items-center gap-3 p-3 rounded-lg border border-gray-200 dark:border-gray-700 cursor-pointer">
                    <input type="checkbox" wire:model="show_offers_banner" class="rounded text-primary-600 focus:ring-primary-500">
                    <span class="text-sm font-medium text-gray-800 dark:text-gray-200">{{ __('بانر العروض والتخفيضات') }}</span>
                </label>

                <label class="flex items-center gap-3 p-3 rounded-lg border border-gray-200 dark:border-gray-700 cursor-pointer">
                    <input type="checkbox" wire:model="show_latest_products" class="rounded text-primary-600 focus:ring-primary-500">
                    <span class="text-sm font-medium text-gray-800 dark:text-gray-200">{{ __('أحدث المنتجات المضافة') }}</span>
                </label>
            </div>
        </div>

        <!-- 4. محتوى الصفحات الثابتة -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 bg-white dark:bg-gray-900 p-6 rounded-xl border border-gray-200 dark:border-gray-800 shadow-sm">
            <div class="col-span-full">
                <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-1">{{ __('محتوى الصفحات القانونية والتعريفية') }}</h3>
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('تظهر هذه النصوص في صفحات الفوتر والسياسات') }}</p>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">{{ __('عن المتجر (About Us)') }}</label>
                <textarea wire:model="about_us" rows="4" class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm"></textarea>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">{{ __('سياسة الشحن والمرتجع') }}</label>
                <textarea wire:model="shipping_and_returns" rows="4" class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm"></textarea>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">{{ __('الشروط والأحكام') }}</label>
                <textarea wire:model="terms_and_conditions" rows="4" class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm"></textarea>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">{{ __('سياسة الخصوصية') }}</label>
                <textarea wire:model="privacy_policy" rows="4" class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm"></textarea>
            </div>
        </div>

        <!-- زر الحفظ -->
        <div class="flex justify-end gap-3">
            <button type="submit" class="px-6 py-2.5 bg-primary-600 hover:bg-primary-700 text-white font-medium rounded-lg text-sm transition-colors shadow-sm focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2">
                {{ __('حفظ التعديلات') }}
            </button>
        </div>
    </form>
</x-filament-panels::page>
