<x-filament-panels::page>
    <form wire:submit.prevent="save" class="space-y-6">
        <!-- General Shipping Configuration -->
        <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl p-6 shadow-sm space-y-6">
            <h2 class="text-lg font-bold text-gray-900 dark:text-white border-b border-gray-100 dark:border-gray-800 pb-3">
                الإعدادات العامة للشحن والدفع
            </h2>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <!-- Default Rate -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1.5">
                        سعر الشحن الافتراضي ({{ \App\Support\Money::symbol() }}) *
                    </label>
                    <input type="number" step="0.5" wire:model="default_rate_egp" class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white shadow-sm text-sm">
                </div>

                <!-- Free Shipping Threshold -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1.5">
                        شحن مجاني للطلبات فوق ({{ \App\Support\Money::symbol() }})
                    </label>
                    <input type="number" step="10" wire:model="free_shipping_threshold_egp" class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white shadow-sm text-sm">
                    <p class="text-xs text-gray-400 mt-1">ضع 0 للتعطيل.</p>
                </div>

                <!-- COD Fee -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1.5">
                        رسم الدفع عند الاستلام ({{ \App\Support\Money::symbol() }})
                    </label>
                    <input type="number" step="0.5" wire:model="cod_fee_egp" class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white shadow-sm text-sm">
                </div>

                <!-- Default Delivery Time -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1.5">
                        مدة التوصيل الافتراضية
                    </label>
                    <input type="text" wire:model="default_delivery_time_ar" class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white shadow-sm text-sm">
                </div>

                <!-- Allow COD Toggle -->
                <div class="flex items-center gap-3 pt-6">
                    <input type="checkbox" id="allow_cod" wire:model="allow_cod" class="w-5 h-5 text-primary-600 rounded">
                    <label for="allow_cod" class="text-sm font-semibold text-gray-700 dark:text-gray-300">
                        تفعيل خيار الدفع عند الاستلام (COD)
                    </label>
                </div>
            </div>
        </div>

        <!-- Governorate Specific Rates Table -->
        <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl p-6 shadow-sm space-y-4">
            <h2 class="text-lg font-bold text-gray-900 dark:text-white border-b border-gray-100 dark:border-gray-800 pb-3">
                تخصيص أسعار ومواعيد الشحن لكل محافظة مصرية
            </h2>
            <p class="text-xs text-gray-500">
                يمكنك تخصيص سعر شحن خاص أو موعد تسليم لكل محافظة، أو إيقاف التوصيل لمحافظة معينة.
            </p>

            <div class="overflow-x-auto">
                <table class="w-full text-sm text-right divide-y divide-gray-100 dark:divide-gray-800">
                    <thead class="bg-gray-50 dark:bg-gray-800/50 text-gray-700 dark:text-gray-300 text-xs font-bold uppercase">
                        <tr>
                            <th class="px-4 py-3">المحافظة</th>
                            <th class="px-4 py-3">سعر الشحن ({{ \App\Support\Money::symbol() }})</th>
                            <th class="px-4 py-3">مدة التوصيل المتوقعة</th>
                            <th class="px-4 py-3 text-center">التوصيل متاح</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @php
                            $governorates = \App\Support\Location\Governorate::forCountries();
                        @endphp
                        @foreach($governorates as $gov)
                            <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-800/30">
                                <td class="px-4 py-3 font-semibold text-gray-900 dark:text-white">
                                    {{ $gov->label() }}
                                </td>
                                <td class="px-4 py-2 w-40">
                                    <input type="number" step="0.5" wire:model="governorate_rates.{{ $gov->id }}.rate_egp" class="w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm py-1.5">
                                </td>
                                <td class="px-4 py-2 w-64">
                                    <input type="text" wire:model="governorate_rates.{{ $gov->id }}.delivery_time_ar" class="w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-sm py-1.5">
                                </td>
                                <td class="px-4 py-2 text-center w-24">
                                    <input type="checkbox" wire:model="governorate_rates.{{ $gov->id }}.is_active" class="w-4 h-4 text-primary-600 rounded">
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Submit Button -->
        <div class="flex justify-end">
            <x-filament::button type="submit" size="lg">
                حفظ إعدادات الشحن
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
