<x-filament-panels::page>
    <div class="space-y-6" dir="rtl">
        <!-- 1. Subscription Status Card -->
        <div class="p-6 bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 flex flex-col md:flex-row items-start md:items-center justify-between gap-6">
            <div class="flex items-center gap-4">
                <div class="w-14 h-14 rounded-2xl bg-indigo-50 dark:bg-indigo-950/50 text-indigo-600 dark:text-indigo-400 flex items-center justify-center font-bold text-2xl border border-indigo-100 dark:border-indigo-900">
                    <x-heroicon-o-sparkles class="w-7 h-7" />
                </div>
                <div>
                    <div class="flex items-center gap-3">
                        <h2 class="text-xl font-bold text-gray-900 dark:text-white">
                            {{ $currentPlan ? $currentPlan->name : __('لا توجد خطة مفعلة') }}
                        </h2>
                        @if($subscription)
                            @if($subscription->isTrial())
                                <span class="px-3 py-1 rounded-full text-xs font-semibold bg-amber-50 text-amber-700 border border-amber-200 dark:bg-amber-950/40 dark:text-amber-400 dark:border-amber-900">
                                    {{ __('فترة تجريبية (متبقي') }} {{ $subscription->daysLeftInTrial() }} {{ __('يوم)') }}
                                </span>
                            @elseif($subscription->isActive())
                                <span class="px-3 py-1 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-400 dark:border-emerald-900">
                                    {{ __('نشط (التجديد خلال') }} {{ $subscription->daysLeftInPeriod() }} {{ __('يوم)') }}
                                </span>
                            @elseif($subscription->isPastDue())
                                <span class="px-3 py-1 rounded-full text-xs font-semibold bg-rose-50 text-rose-700 border border-rose-200 dark:bg-rose-950/40 dark:text-rose-400 dark:border-rose-900 animate-pulse">
                                    {{ __('متأخر السداد (مهلة سماح نشطة)') }}
                                </span>
                            @elseif($subscription->isSuspended())
                                <span class="px-3 py-1 rounded-full text-xs font-semibold bg-rose-100 text-rose-800 border border-rose-300 dark:bg-rose-900 dark:text-rose-200">
                                    {{ __('موقوف مؤقتًا لعدم السداد') }}
                                </span>
                            @endif
                        @endif
                    </div>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                        @if($currentPlan)
                            {{ $currentPlan->description }}
                        @else
                            {{ __('يرجى اختيار خطة اشتراك للبدء في تشغيل متجرك.') }}
                        @endif
                    </p>
                </div>
            </div>

            <div class="flex items-center gap-3 w-full md:w-auto">
                <button
                    type="button"
                    wire:click="openRenewalModal"
                    class="w-full md:w-auto inline-flex items-center justify-center px-5 py-2.5 rounded-xl font-semibold text-sm text-white bg-indigo-600 hover:bg-indigo-700 shadow-sm transition"
                >
                    <x-heroicon-o-arrow-up-circle class="w-5 h-5 ml-2" />
                    {{ __('تجديد أو ترقية الخطة') }}
                </button>
            </div>
        </div>

        <!-- 2. Resource Usage Grid -->
        <div>
            <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-4">{{ __('استهلاك الموارد والحدود') }}</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
                @foreach($usageStats as $key => $stat)
                    <div class="p-5 bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700">
                        <div class="flex items-center justify-between text-sm mb-2">
                            <span class="font-medium text-gray-700 dark:text-gray-300">{{ $stat['label'] }}</span>
                            <span class="font-bold text-gray-900 dark:text-white">
                                {{ $stat['used'] }}
                                @if($stat['max'] === -1)
                                    / <span class="text-indigo-600 dark:text-indigo-400">{{ __('غير محدود') }}</span>
                                @else
                                    / {{ $stat['max'] }}
                                @endif
                            </span>
                        </div>
                        <div class="w-full bg-gray-100 dark:bg-gray-700 h-2.5 rounded-full overflow-hidden">
                            @php
                                $barColor = $stat['percentage'] > 90 ? 'bg-rose-500' : ($stat['percentage'] > 75 ? 'bg-amber-500' : 'bg-indigo-600');
                            @endphp
                            <div class="{{ $barColor }} h-2.5 rounded-full transition-all duration-500" style="width: {{ $stat['percentage'] }}%;"></div>
                        </div>
                        <div class="mt-2 text-xs text-gray-400 text-left">
                            {{ $stat['max'] === -1 ? __('مفتوح بالكامل') : $stat['percentage'] . __('% مُستهلك') }}
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <!-- 3. Features Matrix -->
        <div>
            <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-4">{{ __('الميزات المفعلة في خطتك') }}</h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                @foreach($featureStats as $key => $feature)
                    <div class="p-4 rounded-xl border flex items-center justify-between {{ $feature['enabled'] ? 'bg-emerald-50/40 border-emerald-200 dark:bg-emerald-950/20 dark:border-emerald-800' : 'bg-gray-50 border-gray-200 dark:bg-gray-800/50 dark:border-gray-700 opacity-60' }}">
                        <span class="text-sm font-semibold {{ $feature['enabled'] ? 'text-gray-900 dark:text-white' : 'text-gray-500 dark:text-gray-400' }}">
                            {{ $feature['label'] }}
                        </span>
                        @if($feature['enabled'])
                            <x-heroicon-s-check-circle class="w-5 h-5 text-emerald-600 dark:text-emerald-400" />
                        @else
                            <x-heroicon-s-lock-closed class="w-4 h-4 text-gray-400" />
                        @endif
                    </div>
                @endforeach
            </div>
        </div>

        <!-- 4. Invoices Table -->
        <div>
            <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-4">{{ __('سجل الفواتير والمدفوعات') }}</h3>
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
                @if($invoices->isEmpty())
                    <div class="p-8 text-center text-gray-400">
                        {{ __('لا توجد فواتير مسجلة حتى الآن.') }}
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-right text-sm">
                            <thead class="bg-gray-50 dark:bg-gray-900/50 text-gray-600 dark:text-gray-400 border-b border-gray-200 dark:border-gray-700">
                                <tr>
                                    <th class="p-4">{{ __('رقم الفاتورة') }}</th>
                                    <th class="p-4">{{ __('الفترة') }}</th>
                                    <th class="p-4">{{ __('المبلغ') }}</th>
                                    <th class="p-4">{{ __('طريقة الدفع') }}</th>
                                    <th class="p-4">{{ __('الحالة') }}</th>
                                    <th class="p-4 text-center">{{ __('الإجراء') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                @foreach($invoices as $inv)
                                    <tr>
                                        <td class="p-4 font-mono font-semibold">{{ $inv->invoice_number }}</td>
                                        <td class="p-4 text-gray-500">
                                            {{ $inv->billing_period_starts_at->format('Y-m-d') }} {{ __('إلى') }} {{ $inv->billing_period_ends_at->format('Y-m-d') }}
                                        </td>
                                        <td class="p-4 font-bold">{{ $inv->amountMoney()->format() }}</td>
                                        <td class="p-4 text-gray-500">{{ $inv->payment_method ?? __('تحويل بنكي') }}</td>
                                        <td class="p-4">
                                            @if($inv->isPaid())
                                                <span class="px-2.5 py-1 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">{{ __('مدفوعة') }}</span>
                                            @elseif($inv->isPending())
                                                <span class="px-2.5 py-1 rounded-full text-xs font-semibold bg-amber-50 text-amber-700 border border-amber-200">{{ __('قيد المراجعة') }}</span>
                                            @else
                                                <span class="px-2.5 py-1 rounded-full text-xs font-semibold bg-rose-50 text-rose-700 border border-rose-200">{{ __('مرفوضة') }}</span>
                                            @endif
                                        </td>
                                        <td class="p-4 text-center">
                                            <a
                                                href="{{ route('billing.invoices.download', $inv) }}"
                                                target="_blank"
                                                class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg text-xs font-semibold text-indigo-600 hover:bg-indigo-50 dark:hover:bg-indigo-950 transition"
                                            >
                                                <x-heroicon-o-arrow-down-tray class="w-4 h-4" />
                                                {{ __('تحميل PDF') }}
                                            </a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>

        <!-- 5. Renewal Modal -->
        @if($showRenewalModal)
            <div class="fixed inset-0 z-50 overflow-y-auto bg-black/60 backdrop-blur-sm flex items-center justify-center p-4">
                <div class="bg-white dark:bg-gray-800 rounded-3xl max-w-2xl w-full p-6 sm:p-8 shadow-2xl border border-gray-100 dark:border-gray-700 relative text-right">
                    <button wire:click="closeRenewalModal" class="absolute top-5 left-5 text-gray-400 hover:text-gray-600">
                        <x-heroicon-o-x-mark class="w-6 h-6" />
                    </button>

                    <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-2">{{ __('تجديد أو ترقية الخطة') }}</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mb-6">{{ __('اختر الخطة المناسبة وأرفق إيصال السداد للاعتماد الفوري.') }}</p>

                    <form wire:submit="submitRenewal" class="space-y-5">
                        <!-- Select Plan -->
                        <div>
                            <label class="block text-sm font-semibold mb-2">{{ __('اختر الباقة') }}</label>
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                @foreach($availablePlans as $p)
                                    <label class="p-4 border rounded-2xl cursor-pointer transition {{ $selected_plan_id === $p->id ? 'border-indigo-600 bg-indigo-50/40 dark:bg-indigo-950/30' : 'border-gray-200 dark:border-gray-700 hover:border-gray-300' }}">
                                        <input type="radio" wire:model.live="selected_plan_id" value="{{ $p->id }}" class="sr-only">
                                        <div class="font-bold text-sm">{{ $p->name }}</div>
                                        <div class="text-xs text-indigo-600 dark:text-indigo-400 font-bold mt-1">
                                            {{ $selected_cycle === 'yearly' ? $p->priceYearlyMoney()->format() . __(' / سنوياً') : $p->priceMonthlyMoney()->format() . __(' / شهرياً') }}
                                        </div>
                                    </label>
                                @endforeach
                            </div>
                        </div>

                        <!-- Select Cycle -->
                        <div>
                            <label class="block text-sm font-semibold mb-2">{{ __('دورة الفوترة') }}</label>
                            <div class="flex gap-4">
                                <label class="flex items-center gap-2 cursor-pointer text-sm font-medium">
                                    <input type="radio" wire:model.live="selected_cycle" value="monthly">
                                    {{ __('اشتراك شهري') }}
                                </label>
                                <label class="flex items-center gap-2 cursor-pointer text-sm font-medium text-emerald-600">
                                    <input type="radio" wire:model.live="selected_cycle" value="yearly">
                                    {{ __('اشتراك سنوي (خصم شهرين مجانًا)') }}
                                </label>
                            </div>
                        </div>

                        <!-- Payment Instructions Box -->
                        <div class="p-4 rounded-xl bg-gray-50 dark:bg-gray-900/60 border border-gray-200 dark:border-gray-700 text-xs space-y-2 text-gray-600 dark:text-gray-300">
                            <div class="font-bold text-gray-800 dark:text-gray-200">{{ __('بيانات التحويل المعتمدة:') }}</div>
                            <div>• <strong>{{ __('إنستاباي (Instapay):') }}</strong> affiliateos@instapay</div>
                            <div>• <strong>{{ __('فودافون كاش:') }}</strong> 01000000000</div>
                            <div>• <strong>{{ __('حساب بنك مصر:') }}</strong> EG1200000000000000000000000</div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium mb-1">{{ __('وسيلة التحويل') }}</label>
                                <select wire:model="payment_method" class="w-full rounded-xl border-gray-300 dark:border-gray-700 text-sm">
                                    <option value="manual_instapay">{{ __('إنستاباي (Instapay)') }}</option>
                                    <option value="manual_vodafone_cash">{{ __('فودافون كاش (Vodafone Cash)') }}</option>
                                    <option value="manual_bank_transfer">{{ __('تحويل بنكي مباشر') }}</option>
                                </select>
                            </div>

                            <div>
                                <label class="block text-sm font-medium mb-1">{{ __('الرقم المرجعي / رقم العملية') }}</label>
                                <input type="text" wire:model="payment_reference" placeholder="{{ __('مثال: REF-928374') }}" class="w-full rounded-xl border-gray-300 dark:border-gray-700 text-sm">
                                @error('payment_reference') <span class="text-xs text-rose-500">{{ $message }}</span> @enderror
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium mb-1">{{ __('صورة إيصال التحويل') }}</label>
                            <input type="file" wire:model="receipt_file" class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100">
                            @error('receipt_file') <span class="text-xs text-rose-500">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-medium mb-1">{{ __('ملاحظات إضافية (اختياري)') }}</label>
                            <textarea wire:model="notes" rows="2" class="w-full rounded-xl border-gray-300 dark:border-gray-700 text-sm"></textarea>
                        </div>

                        <div class="flex justify-end gap-3 pt-4 border-t border-gray-100 dark:border-gray-700">
                            <button type="button" wire:click="closeRenewalModal" class="px-5 py-2.5 text-sm font-medium text-gray-600 hover:bg-gray-100 dark:text-gray-300 rounded-xl">{{ __('إلغاء') }}</button>
                            <button type="submit" class="px-5 py-2.5 text-sm font-semibold text-white bg-indigo-600 hover:bg-indigo-700 rounded-xl shadow-sm">{{ __('إرسال طلب التجديد') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        @endif
    </div>
</x-filament-panels::page>
